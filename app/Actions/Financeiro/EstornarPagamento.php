<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Pagamento;
use App\Models\PagamentoTaxa;
use App\Models\TaxaCondominial;
use App\Services\StatusTaxaService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Estorno no modelo novo (substitui EstornarPagamento no cutover): cria
 * pagamento filho com valor NEGATIVO e estorno_de_id (sem o flag `estornado`
 * do legado — "já estornado" = já existe estorno filho). Mesmos tetos do
 * legado (RN-16..RN-18 + correção P10/EX-02): não superar o aplicado pelo
 * pagamento original nem o acumulado atual da taxa. Status recalculado pelo
 * serviço único (a "reabertura" RN-18 é consequência natural da soma).
 */
class EstornarPagamento
{
    public function __construct(private readonly StatusTaxaService $statusService) {}

    /**
     * @param  array<int, float>  $valoresPorTaxa  [taxa_id => valor a estornar]
     */
    public function executar(Pagamento $pagamento, array $valoresPorTaxa): Pagamento
    {
        if ($pagamento->estornos()->exists()) {
            throw new DomainException('Este pagamento já foi estornado.');
        }

        if ($pagamento->isEstorno()) {
            throw new DomainException('Não é possível estornar um registro de estorno.');
        }

        $valores = array_filter($valoresPorTaxa, fn (float $v): bool => $v > 0);

        if ($valores === []) {
            throw new DomainException('Informe ao menos um valor para estorno.');
        }

        return DB::transaction(function () use ($pagamento, $valores): Pagamento {
            $estorno = Pagamento::query()->create([
                'data_pagamento' => now()->toDateString(),
                'descricao' => 'Estorno do pagamento #'.$pagamento->id,
                'valor_total' => -array_sum($valores),
                'unidade_id' => $pagamento->unidade_id,
                'pessoa_id' => $pagamento->pessoa_id,
                'forma_pagamento' => $pagamento->forma_pagamento,
                'estorno_de_id' => $pagamento->id,
            ]);

            foreach ($valores as $taxaId => $valorEstorno) {
                $taxa = TaxaCondominial::query()->lockForUpdate()->findOrFail($taxaId);

                $pivot = PagamentoTaxa::query()
                    ->where('pagamento_id', $pagamento->id)
                    ->where('taxa_condominial_id', $taxaId)
                    ->first();

                if ($pivot === null) {
                    throw new DomainException(
                        "A taxa {$taxa->competencia_mes}/{$taxa->competencia_ano} não pertence a este pagamento."
                    );
                }

                // Teto 1 (RN-17): não superar o aplicado pelo pagamento original
                if ($valorEstorno > (float) $pivot->valor_aplicado) {
                    throw new DomainException(
                        'Valor de estorno maior que o valor pago para a taxa '
                        .$taxa->competencia_mes.'/'.$taxa->competencia_ano
                    );
                }

                // Teto 2 (P10/EX-02): não superar o acumulado atual da taxa
                $acumulado = (float) ($taxa->pagamentoTaxas()->sum('valor_aplicado') ?: 0);

                if ($valorEstorno > $acumulado) {
                    throw new DomainException(
                        'Valor de estorno excede o total pago acumulado da taxa '
                        .$taxa->competencia_mes.'/'.$taxa->competencia_ano
                    );
                }

                PagamentoTaxa::query()->create([
                    'pagamento_id' => $estorno->id,
                    'taxa_condominial_id' => $taxa->id,
                    'valor_aplicado' => -abs($valorEstorno),
                ]);

                $this->statusService->recalcular($taxa);
            }

            return $estorno;
        });
    }
}
