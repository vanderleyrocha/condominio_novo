<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PapelUsuario;
use App\Enums\ResponsavelPagamento;
use App\Models\CobrancaExtra;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ETL do legado (data_migration_plan.md): idempotente por reconstrução —
 * cada execução trunca o destino e reprocessa tudo a partir do schema stage.
 *
 * Uso:
 *   php artisan migrar:legado --dump=D:\caminho\dump.sql   (restaura o dump no stage e migra)
 *   php artisan migrar:legado --stage=legado_stage          (usa stage já restaurado)
 */
class MigrarLegado extends Command
{
    protected $signature = 'migrar:legado {--dump=} {--stage=legado_stage}';

    protected $description = 'Migra os dados do banco legado para o schema novo, com backfill e relatório de reconciliação';

    /** @var list<string> */
    private array $relatorio = [];

    public function handle(): int
    {
        $stage = (string) $this->option('stage');

        if ($this->option('dump')) {
            $this->restaurarDump((string) $this->option('dump'), $stage);
        }

        $this->configurarConexaoStage($stage);

        DB::connection('legado_stage')->getPdo(); // valida acesso ao stage

        $this->info("Migrando de `{$stage}` para `".DB::connection()->getDatabaseName().'`...');

        // Sem transação externa: TRUNCATE comita implicitamente no MySQL.
        // A idempotência vem da reconstrução — qualquer falha, re-executar do zero.
        $this->truncarDestino();
        $this->migrarUsers();
        $this->migrarTabelaDireta('accesses', ['id', 'user_id', 'datetime', 'created_at', 'updated_at']);
        $this->migrarProprietarios();
        $this->migrarTabelaDireta('imoveis', ['id', 'proprietario_id', 'nome', 'created_at', 'updated_at']);
        $this->migrarMensalidades();
        $this->migrarTabelaDireta('despesa_tipos', ['id', 'descricao', 'created_at', 'updated_at']);
        $this->migrarDespesas();
        $this->migrarTabelaDireta('ipcas', ['id', 'ano', 'mes', 'indice', 'created_at', 'updated_at']);
        $this->migrarPagamentos();
        $this->migrarPivotPagamentos();
        $this->migrarCobrancaExtraHistorica();
        $this->migrarReceitas();
        $this->log("Stage utilizado: {$stage}");

        $this->reconciliar();
        $this->gravarRelatorio();

        $this->info('Migração concluída. Relatório em storage/app/migracao/.');

        return self::SUCCESS;
    }

    private function restaurarDump(string $caminho, string $stage): void
    {
        if (! is_file($caminho)) {
            $this->fail("Dump não encontrado: {$caminho}");
        }

        $this->info("Restaurando dump em `{$stage}`...");
        DB::statement("DROP DATABASE IF EXISTS `{$stage}`");
        DB::statement("CREATE DATABASE `{$stage}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $config = config('database.connections.mysql');
        $senha = $config['password'] !== '' ? '-p'.escapeshellarg($config['password']) : '';
        $cmd = sprintf(
            'mysql -h %s -P %s -u %s %s %s < %s',
            escapeshellarg($config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            $senha,
            escapeshellarg($stage),
            escapeshellarg($caminho),
        );

        exec($cmd, $output, $codigo);

        if ($codigo !== 0) {
            $this->fail("Falha ao restaurar o dump (exit {$codigo}).");
        }
    }

    private function configurarConexaoStage(string $stage): void
    {
        config(['database.connections.legado_stage' => array_merge(
            config('database.connections.mysql'),
            ['database' => $stage],
        )]);
    }

    private function stage(): \Illuminate\Database\Connection
    {
        return DB::connection('legado_stage');
    }

    private function truncarDestino(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'cobranca_extra_mensalidade', 'cobrancas_extras', 'receitas',
            'pagamento_mensalidades', 'pagamentos', 'ipcas', 'despesas', 'despesa_tipos',
            'mensalidades', 'imoveis', 'proprietarios', 'accesses', 'users',
        ] as $tabela) {
            DB::table($tabela)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function migrarUsers(): void
    {
        $semPapelClaro = 0;

        foreach ($this->stage()->table('users')->orderBy('id')->get() as $u) {
            // Precedência admin > level-one (paridade com a ordem dos Gates do legado)
            if (! empty($u->is_admin)) {
                $papel = PapelUsuario::Admin;
            } elseif ((int) ($u->level ?? 0) === 1) {
                $papel = PapelUsuario::LevelOne;
            } else {
                $papel = PapelUsuario::LevelOne;
                $semPapelClaro++;
                $this->log("AVISO users#{$u->id} ({$u->name}): sem papel claro no legado — atribuído level_one");
            }

            DB::table('users')->insert([
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'email_verified_at' => $u->email_verified_at,
                'password' => $u->password, // hash bcrypt preservado
                'papel' => $papel->value,
                'remember_token' => $u->remember_token,
                'created_at' => $u->created_at,
                'updated_at' => $u->updated_at,
            ]);
        }

        $this->log('users migrados: '.DB::table('users')->count()." (sem papel claro: {$semPapelClaro})");
    }

    private function migrarProprietarios(): void
    {
        foreach ($this->stage()->table('proprietarios')->orderBy('id')->get() as $p) {
            $cpf = preg_replace('/\D/', '', (string) $p->cpf) ?? '';
            if (strlen($cpf) !== 11) {
                // Placeholder único por proprietário (prefixo 999 nunca é CPF válido);
                // preserva a unique e sinaliza correção manual no relatório
                $cpf = sprintf('999%08d', $p->id);
                $this->log("PENDÊNCIA proprietarios#{$p->id} ({$p->nome}): CPF ausente/inválido no legado ('{$p->cpf}') — placeholder {$cpf}; corrigir manualmente");
            }

            $responsavel = match ($p->quem_paga) {
                'nome' => ResponsavelPagamento::Proprietario,
                'nome_inquilino' => ResponsavelPagamento::Inquilino,
                default => ResponsavelPagamento::Proprietario,
            };
            if (! in_array($p->quem_paga, ['nome', 'nome_inquilino'], true)) {
                $this->log("AVISO proprietarios#{$p->id}: quem_paga='{$p->quem_paga}' — assumido proprietario");
            }

            DB::table('proprietarios')->insert([
                'id' => $p->id,
                'nome' => $p->nome,
                'cpf' => $cpf,
                'telefone' => (string) ($p->telefone ?? ''),
                'nome_inquilino' => $p->nome_inquilino,
                'cpf_inquilino' => $p->cpf_inquilino ? substr(preg_replace('/\D/', '', $p->cpf_inquilino) ?? '', 0, 11) ?: null : null,
                'telefone_inquilino' => $p->telefone_inquilino,
                'responsavel_pagamento' => $responsavel->value,
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
            ]);
        }

        $this->log('proprietarios migrados: '.DB::table('proprietarios')->count());
    }

    private function migrarMensalidades(): void
    {
        $backfill = 0;

        // Detecta duplicatas (N-01) antes da carga
        $duplicatas = $this->stage()->table('mensalidades')
            ->select('imovel_id', 'ano', 'mes', DB::raw('COUNT(*) as n'))
            ->groupBy('imovel_id', 'ano', 'mes')->having('n', '>', 1)->get();
        foreach ($duplicatas as $d) {
            $this->log("PENDÊNCIA N-01: mensalidade duplicada imovel={$d->imovel_id} {$d->mes}/{$d->ano} ({$d->n}x) — migrada sem deduplicar; decisão manual");
        }

        foreach ($this->stage()->table('mensalidades')->orderBy('id')->get() as $m) {
            $pagoEm = $m->pago_em;

            // Backfill decidido em P3 (EX-04): pago_em = vencimento quando pago sem data
            if ($pagoEm === null && (float) ($m->valor_pago ?? 0) > 0) {
                $pagoEm = $m->vencimento;
                $backfill++;
            }

            DB::table('mensalidades')->insert([
                'id' => $m->id,
                'imovel_id' => $m->imovel_id,
                'mes' => $m->mes,
                'ano' => $m->ano,
                'vencimento' => $m->vencimento,
                'valor' => $m->valor ?? 0,
                'desconto' => $m->desconto ?? 0,
                'acrescimo' => $m->acrescimo ?? 0,
                'valor_pago' => $m->valor_pago ?? 0,
                'pago_em' => $pagoEm,
                'contabilizado' => (bool) ($m->contabilizado ?? 1),
                'created_at' => $m->created_at,
                'updated_at' => $m->updated_at,
            ]);
        }

        $this->log('mensalidades migradas: '.DB::table('mensalidades')->count()." (backfill pago_em=vencimento: {$backfill})");
    }

    private function migrarDespesas(): void
    {
        foreach ($this->stage()->table('despesas')->orderBy('id')->get() as $d) {
            // Validação mes/ano redundantes: prevalece `data` (data_migration_plan)
            $mesData = (int) date('n', strtotime((string) $d->data));
            $anoData = (int) date('Y', strtotime((string) $d->data));
            if (isset($d->mes) && ((int) $d->mes !== $mesData || (int) $d->ano !== $anoData)) {
                $this->log("AVISO despesas#{$d->id}: mes/ano ({$d->mes}/{$d->ano}) divergem de data ({$d->data}) — prevaleceu data");
            }

            DB::table('despesas')->insert([
                'id' => $d->id,
                'despesa_tipo_id' => $d->despesa_tipo_id,
                'data' => $d->data,
                'descricao' => (string) ($d->descricao ?? ''),
                'valor' => $d->valor ?? 0,
                'contabilizado' => (bool) ($d->contabilizado ?? 1),
                'created_at' => $d->created_at,
                'updated_at' => $d->updated_at,
            ]);
        }

        $this->log('despesas migradas: '.DB::table('despesas')->count());
    }

    private function migrarPagamentos(): void
    {
        foreach ($this->stage()->table('pagamentos')->orderBy('id')->get() as $p) {
            DB::table('pagamentos')->insert([
                'id' => $p->id,
                'proprietario_id' => $p->proprietario_id,
                // Legado usa 0 no lugar de NULL nas FKs opcionais — normaliza
                'imovel_id' => $p->imovel_id ?: null,
                'pagamento_origem_id' => $p->pagamento_origem_id ?: null,
                'data' => $p->data,
                'descricao' => (string) ($p->descricao ?? ''),
                'valor' => $p->valor ?? 0,
                'estornado' => (bool) ($p->estornado ?? 0),
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
            ]);
        }

        $this->log('pagamentos migrados: '.DB::table('pagamentos')->count());
    }

    private function migrarPivotPagamentos(): void
    {
        $divergentes = 0;

        foreach ($this->stage()->table('pagamento_mensalidades')->orderBy('id')->get() as $pm) {
            // Validação da coluna redundante imovel_id antes de descartá-la
            $imovelDaMensalidade = DB::table('mensalidades')->where('id', $pm->mensalidade_id)->value('imovel_id');
            if (isset($pm->imovel_id) && (int) $pm->imovel_id !== (int) $imovelDaMensalidade) {
                $divergentes++;
                $this->log("PENDÊNCIA pagamento_mensalidades#{$pm->id}: imovel_id ({$pm->imovel_id}) ≠ mensalidade.imovel_id ({$imovelDaMensalidade})");
            }

            DB::table('pagamento_mensalidades')->insert([
                'id' => $pm->id,
                'pagamento_id' => $pm->pagamento_id,
                'mensalidade_id' => $pm->mensalidade_id,
                'valor' => $pm->valor ?? 0,
                'created_at' => $pm->created_at,
                'updated_at' => $pm->updated_at,
            ]);
        }

        if ($divergentes > 0) {
            throw new \RuntimeException("ETL abortado: {$divergentes} pivots com imovel_id divergente (ver relatório).");
        }

        $this->log('pagamento_mensalidades migrados: '.DB::table('pagamento_mensalidades')->count());
    }

    private function migrarCobrancaExtraHistorica(): void
    {
        // Materializa a "poupança" (RN-26/P2): mensalidades pagas com exatamente R$ 150
        // vinculam R$ 50 à cobrança extra histórica. Vigência apurada do próprio dado (L-02).
        $historicas = DB::table('mensalidades')->where('valor_pago', 150)->orderBy('vencimento')->get();

        if ($historicas->isEmpty()) {
            $this->log('cobrança extra histórica: nenhuma mensalidade de R$ 150,00 encontrada');

            return;
        }

        $cobranca = CobrancaExtra::query()->create([
            'nome' => 'Poupança pintura do prédio',
            'valor' => '50.00',
            'vigencia_inicio' => $historicas->first()->vencimento,
            'vigencia_fim' => $historicas->last()->vencimento,
            'ativa' => true,
        ]);

        $agora = now();
        DB::table('cobranca_extra_mensalidade')->insert(
            $historicas->map(fn ($m) => [
                'cobranca_extra_id' => $cobranca->id,
                'mensalidade_id' => $m->id,
                'valor' => '50.00',
                'created_at' => $agora,
                'updated_at' => $agora,
            ])->all(),
        );

        $this->log('cobrança extra histórica: '.$historicas->count().' mensalidades vinculadas (paridade RN-26: '.$historicas->count().' × R$ 50,00)');
    }

    private function migrarReceitas(): void
    {
        $cobrancaId = DB::table('cobrancas_extras')->value('id');
        $juros = 0;

        foreach ($this->stage()->table('receitas')->orderBy('id')->get() as $r) {
            // RN-27: receitas pós-2024-12-31 eram "juros de poupança" — vinculadas à cobrança extra
            $vinculada = $cobrancaId !== null && (string) $r->data > '2024-12-31';
            if ($vinculada) {
                $juros++;
            }

            DB::table('receitas')->insert([
                'id' => $r->id,
                'data' => $r->data,
                'descricao' => (string) ($r->descricao ?? ''),
                'valor' => $r->valor ?? 0,
                'contabilizado' => (bool) ($r->contabilizado ?? 1),
                'cobranca_extra_id' => $vinculada ? $cobrancaId : null,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ]);
        }

        $this->log('receitas migradas: '.DB::table('receitas')->count()." (vinculadas como juros: {$juros})");
    }

    /**
     * Cópia direta de tabela sem transformação.
     *
     * @param list<string> $colunas
     */
    private function migrarTabelaDireta(string $tabela, array $colunas, ?string $tabelaOrigem = null): void
    {
        $origem = $tabelaOrigem ?? $tabela;

        foreach ($this->stage()->table($origem)->orderBy('id')->get() as $linha) {
            $dados = [];
            foreach ($colunas as $coluna) {
                $dados[$coluna] = $linha->{$coluna} ?? null;
            }
            DB::table($tabela)->insert($dados);
        }

        $this->log("{$tabela} migrados: ".DB::table($tabela)->count());
    }

    private function reconciliar(): void
    {
        $this->log('');
        $this->log('=== RECONCILIAÇÃO (origem × destino) ===');

        $mapa = [
            'users' => 'users', 'accesses' => 'accesses', 'proprietarios' => 'proprietarios',
            'imoveis' => 'imoveis', 'mensalidades' => 'mensalidades', 'pagamentos' => 'pagamentos',
            'pagamento_mensalidades' => 'pagamento_mensalidades', 'despesa_tipos' => 'despesa_tipos',
            'despesas' => 'despesas', 'receitas' => 'receitas', 'ipcas' => 'ipcas',
        ];

        $falhas = 0;
        foreach ($mapa as $origem => $destino) {
            $countOrigem = $this->stage()->table($origem)->count();
            $countDestino = DB::table($destino)->count();
            $ok = $countOrigem === $countDestino ? 'OK' : 'DIVERGENTE';
            if ($ok !== 'OK') {
                $falhas++;
            }
            $this->log(sprintf('%-25s origem=%-6d destino=%-6d %s', $destino, $countOrigem, $countDestino, $ok));
        }

        foreach (['mensalidades' => ['valor', 'valor_pago', 'desconto', 'acrescimo'], 'pagamentos' => ['valor'], 'receitas' => ['valor'], 'despesas' => ['valor']] as $tabela => $colunas) {
            foreach ($colunas as $coluna) {
                $somaOrigem = (string) $this->stage()->table($tabela)->sum($coluna);
                $somaDestino = (string) DB::table($tabela)->sum($coluna);
                $ok = bccomp($somaOrigem ?: '0', $somaDestino ?: '0', 2) === 0 ? 'OK' : 'DIVERGENTE';
                if ($ok !== 'OK') {
                    $falhas++;
                }
                $this->log(sprintf('SUM(%s.%s) origem=%s destino=%s %s', $tabela, $coluna, $somaOrigem, $somaDestino, $ok));
            }
        }

        $this->log($falhas === 0 ? 'RECONCILIAÇÃO: TUDO OK' : "RECONCILIAÇÃO: {$falhas} DIVERGÊNCIAS — investigar antes do cutover");
    }

    private function log(string $linha): void
    {
        $this->relatorio[] = $linha;
        $this->line($linha);
    }

    private function gravarRelatorio(): void
    {
        $arquivo = 'migracao/relatorio-'.now()->format('Y-m-d_His').'.txt';
        Storage::disk('local')->put($arquivo, implode("\n", $this->relatorio));
    }
}
