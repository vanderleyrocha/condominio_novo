<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Enums\FormaPagamento;
use App\Models\PagamentoNovo;
use App\Models\PagamentoTaxa;
use App\Models\Pessoa;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Services\StatusTaxaService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Registro de pagamento no modelo novo (substitui RegistrarPagamento no
 * cutover). Mesmas regras de distribuição do legado (RN-11..RN-15):
 * ordem cronológica, min(devido, saldo), excedente não redistribuído,
 * transação atômica com lockForUpdate. Diferenças estruturais: o pagador é
 * uma Pessoa, a unidade é explícita (uma pessoa pode ter várias) e o status
 * da taxa é recalculado pelo serviço único em vez de gravar valor_pago.
 */
class RegistrarPagamentoNovo
{
    public function __construct(private readonly StatusTaxaService $statusService) {}

    /**
     * @param  list<int>  $taxaIds
     */
    public function executar(
        Pessoa $pessoa,
        Unidade $unidade,
        string $data,
        string $descricao,
        float $valor,
        array $taxaIds,
        FormaPagamento $forma = FormaPagamento::NaoInformado,
    ): PagamentoNovo {
        return DB::transaction(function () use ($pessoa, $unidade, $data, $descricao, $valor, $taxaIds, $forma): PagamentoNovo {
            $vinculada = $pessoa->vinculos()
                ->where('unidade_id', $unidade->id)
                ->whereNull('data_fim')
                ->exists();

            if (! $vinculada) {
                throw new DomainException('A pessoa não possui vínculo vigente com esta unidade.');
            }

            $pagamento = PagamentoNovo::query()->create([
                'unidade_id' => $unidade->id,
                'pessoa_id' => $pessoa->id,
                'data_pagamento' => $data,
                'descricao' => $descricao,
                'valor_total' => $valor,
                'forma_pagamento' => $forma,
            ]);

            $saldo = $valor;

            $taxas = TaxaCondominial::query()
                ->whereIn('id', $taxaIds)
                ->where('unidade_id', $unidade->id) // distribuição restrita à unidade
                ->lockForUpdate()
                ->orderBy('competencia_ano')
                ->orderBy('competencia_mes')
                ->get();

            foreach ($taxas as $taxa) {
                // devido restante = valor devido - já pago (RN-13)
                $jaPago = (float) ($taxa->pagamentoTaxas()->sum('valor_aplicado') ?: 0);
                $devido = (float) $taxa->valorDevido() - $jaPago;

                if ($devido <= 0 || $saldo <= 0) {
                    continue; // excedente não redistribuído (RN-12)
                }

                $valorAplicado = min($devido, $saldo);

                PagamentoTaxa::query()->create([
                    'pagamento_id' => $pagamento->id,
                    'taxa_condominial_id' => $taxa->id,
                    'valor_aplicado' => $valorAplicado,
                ]);

                $this->statusService->recalcular($taxa);

                $saldo -= $valorAplicado;
            }

            return $pagamento;
        });
    }
}
