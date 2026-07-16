<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Models\PagamentoMensalidade;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Estorno total/parcial (BR-MIGRAR-006 / RN-16..RN-18): cria Pagamento filho com
 * pivot negativo e pagamento_origem_id; reabre a mensalidade quando valor_pago zera.
 *
 * Correção deliberada do bug do legado (P10 / EX-02): o teto do estorno considera
 * o valor pago pelo pagamento original E o quanto a mensalidade ainda tem de
 * valor_pago — impedindo estorno acima do acumulado quando ela foi quitada por
 * múltiplos pagamentos.
 */
class EstornarPagamento
{
    /**
     * @param array<int, float> $valoresPorMensalidade [mensalidade_id => valor a estornar]
     */
    public function executar(Pagamento $pagamento, array $valoresPorMensalidade): Pagamento
    {
        if ($pagamento->estornado) {
            throw new DomainException('Este pagamento já foi estornado.');
        }

        if ($pagamento->isEstorno()) {
            throw new DomainException('Não é possível estornar um registro de estorno.');
        }

        $valores = array_filter($valoresPorMensalidade, fn (float $v): bool => $v > 0);

        if ($valores === []) {
            throw new DomainException('Informe ao menos um valor para estorno.');
        }

        return DB::transaction(function () use ($pagamento, $valores): Pagamento {
            $estorno = Pagamento::query()->create([
                'data' => now(),
                'descricao' => 'Estorno do pagamento #'.$pagamento->id,
                'valor' => -array_sum($valores),
                'imovel_id' => $pagamento->imovel_id,
                'proprietario_id' => $pagamento->proprietario_id,
                'pagamento_origem_id' => $pagamento->id,
                'estornado' => false,
            ]);

            foreach ($valores as $mensalidadeId => $valorEstorno) {
                $mensalidade = Mensalidade::query()->lockForUpdate()->findOrFail($mensalidadeId);

                $pivot = PagamentoMensalidade::query()
                    ->where('pagamento_id', $pagamento->id)
                    ->where('mensalidade_id', $mensalidadeId)
                    ->first();

                if ($pivot === null) {
                    throw new DomainException(
                        "A mensalidade {$mensalidade->mes}/{$mensalidade->ano} não pertence a este pagamento."
                    );
                }

                // Teto 1 (paridade RN-17): não superar o pago pelo pagamento original
                if ($valorEstorno > (float) $pivot->valor) {
                    throw new DomainException(
                        'Valor de estorno maior que o valor pago para a mensalidade '
                        .$mensalidade->mes.'/'.$mensalidade->ano
                    );
                }

                // Teto 2 (correção P10 / EX-02): não superar o acumulado atual da mensalidade
                if ($valorEstorno > (float) $mensalidade->valor_pago) {
                    throw new DomainException(
                        'Valor de estorno excede o total pago acumulado da mensalidade '
                        .$mensalidade->mes.'/'.$mensalidade->ano
                    );
                }

                PagamentoMensalidade::query()->create([
                    'pagamento_id' => $estorno->id,
                    'mensalidade_id' => $mensalidade->id,
                    'valor' => -abs($valorEstorno),
                ]);

                $novoValorPago = (float) $mensalidade->valor_pago - $valorEstorno;

                // RN-18: valor_pago <= 0 reabre a mensalidade
                if ($novoValorPago <= 0) {
                    $mensalidade->update(['valor_pago' => 0, 'pago_em' => null]);
                } else {
                    $mensalidade->update(['valor_pago' => $novoValorPago]);
                }
            }

            $pagamento->update(['estornado' => true]);

            return $estorno;
        });
    }
}
