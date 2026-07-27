<?php

declare(strict_types=1);

namespace App\Console\Commands\Composicao;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Auditoria da invariante da composição (docs/migration/05-plano-composicao-taxas.md §3.4):
 *
 *     valor_original = SUM(itens_taxa_condominial.valor)
 *
 * Não é expressável como constraint no MySQL, então a garantia é em três
 * camadas: ComposicaoTaxaService como único ponto de escrita, este comando
 * (exit 1 se divergir — serve de gate de deploy) e o teste automatizado.
 */
class VerificarComposicaoTaxas extends Command
{
    protected $signature = 'taxas:verificar-composicao {--limite=20 : Máximo de divergências listadas}';

    protected $description = 'Confere a invariante valor_original = SUM(itens) em todas as taxas condominiais';

    public function handle(): int
    {
        $total = DB::table('taxas_condominiais')->whereNull('deleted_at')->count();

        $somaItens = 'COALESCE((SELECT SUM(i.valor) FROM itens_taxa_condominial i
            WHERE i.taxa_condominial_id = t.id AND i.deleted_at IS NULL), 0)';

        $semItens = DB::table('taxas_condominiais as t')
            ->whereNull('t.deleted_at')
            ->whereNotExists(fn ($q) => $q->from('itens_taxa_condominial as i')
                ->whereColumn('i.taxa_condominial_id', 't.id')
                ->whereNull('i.deleted_at'))
            ->count();

        $divergentes = DB::table('taxas_condominiais as t')
            ->whereNull('t.deleted_at')
            ->whereRaw("t.valor_original <> {$somaItens}")
            ->orderBy('t.id')
            ->limit((int) $this->option('limite'))
            ->get([
                't.id', 't.unidade_id', 't.competencia_ano', 't.competencia_mes', 't.valor_original',
                DB::raw("{$somaItens} as soma_itens"),
            ]);

        $quantidadeDivergente = DB::table('taxas_condominiais as t')
            ->whereNull('t.deleted_at')
            ->whereRaw("t.valor_original <> {$somaItens}")
            ->count();

        $this->table(['Verificação', 'Resultado'], [
            ['taxas conferidas', (string) $total],
            ['sem nenhum item', (string) $semItens],
            ['valor_original ≠ SUM(itens)', (string) $quantidadeDivergente],
        ]);

        if ($quantidadeDivergente === 0 && $semItens === 0) {
            $this->info('Invariante OK em todas as taxas.');

            return self::SUCCESS;
        }

        foreach ($divergentes as $t) {
            $this->line(sprintf(
                '  * Taxa #%d (unidade %d, %02d/%d): valor_original=%s soma_itens=%s',
                $t->id, $t->unidade_id, $t->competencia_mes, $t->competencia_ano,
                $t->valor_original, $t->soma_itens,
            ));
        }

        if ($semItens > 0) {
            $this->warn("{$semItens} taxa(s) sem item — rode `taxas:decompor-composicao` para fazer o backfill.");
        }

        return self::FAILURE;
    }
}
