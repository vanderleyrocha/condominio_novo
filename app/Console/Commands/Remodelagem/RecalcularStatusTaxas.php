<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Services\StatusTaxaService;
use Illuminate\Support\Facades\DB;

/**
 * Passo 8 — deriva o status de TODAS as taxas a partir da soma de
 * pagamento_taxa, usando o serviço ÚNICO de recálculo (BCMath) — o mesmo da
 * aplicação em produção (02-mapeamento-de-para.md §3).
 *
 * Também confere a soma recalculada contra mensalidades.valor_pago do legado
 * (prévia da reconciliação da Fase 3) — divergências indicam inconsistência
 * pré-existente no legado e são apenas logadas.
 */
class RecalcularStatusTaxas extends ComandoRemodelagem
{
    protected $signature = 'migrar:recalcular-status-taxas';

    protected $description = 'Remodelagem: recalcula o status das taxas a partir de pagamento_taxa';

    protected function tabelasDestino(): array
    {
        return []; // só atualiza taxas_condominiais — sem guarda de destino
    }

    protected function entidadesMapa(): array
    {
        return [];
    }

    protected function executar(): int
    {
        $service = new StatusTaxaService;

        /** @var array<int, string> $somas */
        $somas = DB::table('pagamento_taxa')
            ->select('taxa_condominial_id', DB::raw('SUM(valor_aplicado) as total'))
            ->groupBy('taxa_condominial_id')
            ->pluck('total', 'taxa_condominial_id')
            ->all();

        $porStatus = [];
        $atualizacoes = [];

        DB::table('taxas_condominiais')
            ->select('id', 'valor_original', 'valor_desconto', 'valor_acrescimo', 'status')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($taxas) use ($service, $somas, &$porStatus, &$atualizacoes): void {
                foreach ($taxas as $taxa) {
                    $status = $service->calcular(
                        (string) $taxa->valor_original,
                        (string) $taxa->valor_desconto,
                        (string) $taxa->valor_acrescimo,
                        (string) ($somas[$taxa->id] ?? '0'),
                    );

                    $porStatus[$status->value] = ($porStatus[$status->value] ?? 0) + 1;

                    if ($taxa->status !== $status->value) {
                        $atualizacoes[$status->value][] = $taxa->id;
                    }
                }
            });

        foreach ($atualizacoes as $status => $ids) {
            foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                DB::table('taxas_condominiais')->whereIn('id', $chunk)->update(['status' => $status]);
            }
        }

        $resumo = implode(', ', array_map(
            fn (string $s, int $n): string => "{$s}={$n}",
            array_keys($porStatus),
            array_values($porStatus),
        ));
        $this->log('Status recalculados: '.($resumo !== '' ? $resumo : 'nenhuma taxa.'));

        $this->conferirContraLegado($somas);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $somas
     */
    private function conferirContraLegado(array $somas): void
    {
        $divergencias = 0;

        DB::table('mensalidades')->select('id', 'valor_pago')->orderBy('id')
            ->chunk(self::CHUNK, function ($mensalidades) use ($somas, &$divergencias): void {
                foreach ($mensalidades as $m) {
                    $soma = (string) ($somas[$m->id] ?? '0');

                    if (bccomp($soma, (string) $m->valor_pago, 2) !== 0) {
                        $divergencias++;

                        if ($divergencias <= 10) {
                            $this->log(
                                "Divergência legado: mensalidade {$m->id} tem valor_pago={$m->valor_pago}, "
                                ."mas a soma dos pagamentos aplicados é {$soma}."
                            );
                        }
                    }
                }
            });

        $this->log(
            $divergencias === 0
                ? 'Conferência contra mensalidades.valor_pago: nenhuma divergência.'
                : "Conferência contra mensalidades.valor_pago: {$divergencias} divergência(s) — inconsistências pré-existentes do legado, triar antes do cutover."
        );
    }
}
