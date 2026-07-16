<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Models\PagamentoMensalidade;
use App\Models\Proprietario;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Registro de pagamento com distribuição em múltiplas mensalidades (BR-MIGRAR-005).
 * Paridade estrita com PagamentoController::store() do legado (RN-11..RN-15):
 * distribuição em ordem cronológica, min(devido, saldo), excedente não redistribuído,
 * transação atômica com lockForUpdate (RN-14 — invariante de concorrência).
 *
 * @param list<int> $mensalidadeIds
 */
class RegistrarPagamento
{
    /**
     * @param list<int> $mensalidadeIds
     */
    public function executar(
        Proprietario $proprietario,
        string $data,
        string $descricao,
        float $valor,
        array $mensalidadeIds,
    ): Pagamento {
        return DB::transaction(function () use ($proprietario, $data, $descricao, $valor, $mensalidadeIds): Pagamento {
            $imovel = $proprietario->imoveis()->first();

            if ($imovel === null) {
                throw new DomainException('Proprietário sem imóvel vinculado.');
            }

            $pagamento = Pagamento::query()->create([
                'data' => $data,
                'descricao' => $descricao,
                'valor' => $valor,
                'imovel_id' => $imovel->id,
                'proprietario_id' => $proprietario->id,
            ]);

            $saldo = $valor;

            $mensalidades = Mensalidade::query()
                ->whereIn('id', $mensalidadeIds)
                ->where('imovel_id', $imovel->id) // distribuição restrita ao imóvel do proprietário
                ->lockForUpdate()
                ->orderBy('ano')
                ->orderBy('mes')
                ->get();

            foreach ($mensalidades as $mensalidade) {
                // devido = valor + acrescimo - desconto - valor_pago (RN-13)
                $devido = (float) $mensalidade->valor + (float) $mensalidade->acrescimo
                    - (float) $mensalidade->desconto - (float) $mensalidade->valor_pago;

                if ($devido <= 0 || $saldo <= 0) {
                    continue; // excedente não redistribuído (RN-12)
                }

                $valorAplicado = min($devido, $saldo);

                PagamentoMensalidade::query()->create([
                    'pagamento_id' => $pagamento->id,
                    'mensalidade_id' => $mensalidade->id,
                    'valor' => $valorAplicado,
                ]);

                $mensalidade->update([
                    'valor_pago' => (float) $mensalidade->valor_pago + $valorAplicado,
                    'pago_em' => now(),
                ]);

                $saldo -= $valorAplicado;
            }

            return $pagamento;
        });
    }
}
