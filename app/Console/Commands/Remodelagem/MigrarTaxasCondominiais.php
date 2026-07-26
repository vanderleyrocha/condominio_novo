<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\StatusTaxa;
use App\Support\Remodelagem\MapaIds;
use Illuminate\Support\Facades\DB;

/**
 * Passo 5 — mensalidades → taxas_condominiais (02-mapeamento-de-para.md §3).
 * Ids preservados. valor_pago/pago_em NÃO migram: o status é derivado depois
 * por migrar:recalcular-status-taxas a partir de pagamento_taxa.
 *
 * Aborta se houver duplicidade (unidade, ano, mes) na origem — o saneamento
 * N-01 foi feito manualmente; proteção contra restauração de dump antigo.
 */
class MigrarTaxasCondominiais extends ComandoRemodelagem
{
    protected $signature = 'migrar:taxas-condominiais {--truncar}';

    protected $description = 'Remodelagem: mensalidades → taxas condominiais';

    protected function tabelasDestino(): array
    {
        return ['taxas_condominiais'];
    }

    protected function entidadesMapa(): array
    {
        return ['taxa'];
    }

    protected function executar(): int
    {
        $duplicatas = DB::table('mensalidades')
            ->select('imovel_id', 'ano', 'mes', DB::raw('COUNT(*) as total'))
            ->groupBy('imovel_id', 'ano', 'mes')
            ->having('total', '>', 1)
            ->get();

        if ($duplicatas->isNotEmpty()) {
            $this->error('Mensalidades duplicadas na origem (imovel_id/ano/mes) — saneamento N-01 é pré-requisito:');

            foreach ($duplicatas as $d) {
                $this->error("  imovel_id={$d->imovel_id} {$d->mes}/{$d->ano} ({$d->total} registros)");
            }

            return self::FAILURE;
        }

        $total = 0;

        DB::table('mensalidades')->orderBy('id')->chunk(self::CHUNK, function ($mensalidades) use (&$total): void {
            $linhas = [];
            $mapa = [];

            foreach ($mensalidades as $m) {
                $linhas[] = [
                    'id' => $m->id,
                    'unidade_id' => $m->imovel_id,
                    'competencia_mes' => $m->mes,
                    'competencia_ano' => $m->ano,
                    'vencimento' => $m->vencimento,
                    'valor_original' => $m->valor,
                    'valor_desconto' => $m->desconto,
                    'valor_acrescimo' => $m->acrescimo,
                    'status' => StatusTaxa::Aberto->value, // provisório — recalculado no passo 8
                    'contabilizado' => $m->contabilizado,
                    'created_at' => $m->created_at,
                    'updated_at' => $m->updated_at,
                ];
                $mapa[] = ['id_antigo' => (int) $m->id, 'id_novo' => (int) $m->id];
            }

            DB::table('taxas_condominiais')->insert($linhas);
            MapaIds::registrarLote('taxa', $mapa);
            $total += count($linhas);
        });

        $this->log("Taxas migradas: {$total} (ids preservados, status provisório 'aberto').");

        return self::SUCCESS;
    }
}
