<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orquestrador da Fase 2 da remodelagem (04-plano-migracao.md).
 *
 * Idempotente por reconstrução: trunca TODAS as tabelas novas + migration_id_map,
 * executa os 12 passos na ordem de dependência de FK e emite o relatório de
 * reconciliação (contagens e somas financeiras antigo vs novo, via bccomp).
 * Retorna falha se qualquer soma/contagem divergir.
 */
class MigrarRemodelagem extends Command
{
    protected $signature = 'migrar:remodelagem';

    protected $description = 'Remodelagem: reconstrução completa do schema novo a partir do antigo, com reconciliação';

    /** @var list<string> ordem irrelevante — FKs desabilitadas durante o truncate */
    private const TABELAS_NOVAS = [
        'condominio_user',
        'unidade_pessoa',
        'pagamento_taxa',
        'cobranca_extraordinaria_taxa',
        'lancamentos_financeiros',
        'planos_contas',
        'cobrancas_extraordinarias',
        'pagamentos_novo',
        'taxas_condominiais',
        'unidades',
        'pessoas',
        'indices_economicos',
        'configuracoes',
        'condominios',
        'migration_id_map',
    ];

    private const PASSOS = [
        'migrar:condominios',
        'migrar:configuracoes',
        'migrar:pessoas',
        'migrar:unidades',
        'migrar:vinculos',
        'migrar:taxas-condominiais',
        'migrar:pagamentos',
        'migrar:pagamento-taxa',
        'migrar:pagamentos-historicos',
        'migrar:recalcular-status-taxas',
        'migrar:cobrancas-extraordinarias',
        'migrar:lancamentos-financeiros',
        'migrar:indices-economicos',
        'migrar:users-pessoa',
    ];

    public function handle(): int
    {
        $inicio = microtime(true);

        $this->truncarTudo();
        $this->info('Tabelas novas truncadas (reconstrução).');

        foreach (self::PASSOS as $passo) {
            $this->newLine();
            $this->info(">> {$passo}");

            if ($this->call($passo) !== self::SUCCESS) {
                $this->error("Passo {$passo} falhou — migração abortada. Corrija e re-execute migrar:remodelagem.");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $ok = $this->reconciliar();

        $segundos = round(microtime(true) - $inicio, 1);
        $this->newLine();
        $this->info($ok ? "Remodelagem concluída e reconciliada em {$segundos}s." : "Remodelagem executada em {$segundos}s, mas COM DIVERGÊNCIAS — ver acima.");

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function truncarTudo(): void
    {
        // Solta os ponteiros de users antes de truncar pessoas
        DB::table('users')->update(['pessoa_id' => null]);

        Schema::disableForeignKeyConstraints();

        foreach (self::TABELAS_NOVAS as $tabela) {
            DB::table($tabela)->truncate();
        }

        Schema::enableForeignKeyConstraints();
    }

    private function reconciliar(): bool
    {
        $this->info('== Reconciliação antigo vs novo ==');

        $count = fn (string $t): string => (string) DB::table($t)->count();
        $sum = fn (string $t, string $c): string => (string) (DB::table($t)->sum($c) ?: '0');
        $sumNatureza = fn (string $n): string => (string) (DB::table('lancamentos_financeiros')->where('natureza', $n)->sum('valor') ?: '0');

        // Pagamentos sintetizados (históricos) ficam fora da comparação 1:1 com o legado
        $idsHistoricos = DB::table('migration_id_map')->where('entidade', 'pagamento_historico')->pluck('id_novo');
        $migrados = DB::table('pagamentos_novo')->whereNotIn('id', $idsHistoricos);

        $linhas = [
            ['Imóveis → unidades', $count('imoveis'), $count('unidades'), true],
            ['Mensalidades → taxas', $count('mensalidades'), $count('taxas_condominiais'), true],
            ['Pagamentos → pagamentos_novo (sem históricos)', $count('pagamentos'), (string) $migrados->clone()->count(), true],
            ['Pagamentos históricos sintetizados (informativo)', '—', (string) $idsHistoricos->count(), false],
            ['Aplicações (pivot, sem históricos)', $count('pagamento_mensalidades'), (string) DB::table('pagamento_taxa')->whereNotIn('pagamento_id', $idsHistoricos)->count(), true],
            ['Cobranças extras', $count('cobrancas_extras'), $count('cobrancas_extraordinarias'), true],
            ['Pivot cobrança↔taxa', $count('cobranca_extra_mensalidade'), $count('cobranca_extraordinaria_taxa'), true],
            ['Despesas+receitas → lançamentos', (string) (DB::table('despesas')->count() + DB::table('receitas')->count()), $count('lancamentos_financeiros'), true],
            ['IPCA → índices', $count('ipcas'), $count('indices_economicos'), true],
            ['Users → condominio_user', $count('users'), $count('condominio_user'), true],
            ['Proprietários → pessoas (informativo: dedupe N:1 + inquilinos)', $count('proprietarios'), $count('pessoas'), false],
            ['Σ mensalidades.valor vs Σ taxas.valor_original', $sum('mensalidades', 'valor'), $sum('taxas_condominiais', 'valor_original'), true],
            ['Σ desconto', $sum('mensalidades', 'desconto'), $sum('taxas_condominiais', 'valor_desconto'), true],
            ['Σ acréscimo', $sum('mensalidades', 'acrescimo'), $sum('taxas_condominiais', 'valor_acrescimo'), true],
            ['Σ pagamentos.valor vs Σ valor_total (sem históricos)', $sum('pagamentos', 'valor'), (string) ($migrados->clone()->sum('valor_total') ?: '0'), true],
            // Após sintetizar os históricos, toda taxa deve ter soma == valor_pago do legado
            ['Σ mensalidades.valor_pago vs Σ pagamento_taxa', $sum('mensalidades', 'valor_pago'), $sum('pagamento_taxa', 'valor_aplicado'), true],
            ['Σ despesas.valor vs Σ lançamentos despesa', $sum('despesas', 'valor'), $sumNatureza('despesa'), true],
            ['Σ receitas.valor vs Σ lançamentos receita', $sum('receitas', 'valor'), $sumNatureza('receita'), true],
        ];

        $divergencias = 0;
        $tabela = [];

        foreach ($linhas as [$rotulo, $antigo, $novo, $exigeIgualdade]) {
            if ($exigeIgualdade) {
                $igual = bccomp($antigo !== '' ? $antigo : '0', $novo !== '' ? $novo : '0', 2) === 0;
                $status = $igual ? 'OK' : 'DIVERGÊNCIA';

                if (! $igual) {
                    $divergencias++;
                }
            } else {
                $status = '(info)';
            }

            $tabela[] = [$rotulo, $antigo, $novo, $status];
        }

        $this->table(['Verificação', 'Antigo', 'Novo', 'Status'], $tabela);

        if ($divergencias > 0) {
            $this->error("{$divergencias} divergência(s) na reconciliação.");

            return false;
        }

        $this->info('Reconciliação sem divergências.');

        return true;
    }
}
