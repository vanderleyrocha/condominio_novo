<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Mensalidade;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Edição em massa da grade imóvel × mês (BR-MIGRAR-019).
 * Persistência seletiva (paridade com updateYear do legado): só grava a célula
 * cujo valor_pago mudou; ao receber valor > 0, pago_em = hoje; ao zerar, pago_em = null.
 *
 * Divergência deliberada EX-05 (BR-HUMANA-008): cada célula passa pela mesma
 * Policy da edição individual — o legado não verificava autorização aqui.
 */
class AtualizarGradeAnual
{
    /**
     * @param array<int, string> $valoresPagos [mensalidade_id => valor_pago]
     * @return int quantidade de células efetivamente gravadas
     */
    public function executar(array $valoresPagos, User $ator): int
    {
        return DB::transaction(function () use ($valoresPagos, $ator): int {
            $gravadas = 0;

            foreach ($valoresPagos as $mensalidadeId => $valorPago) {
                $mensalidade = Mensalidade::query()->lockForUpdate()->find($mensalidadeId);

                if ($mensalidade === null) {
                    continue;
                }

                $novoValor = (float) $valorPago;

                // Persistência seletiva: ignora célula sem alteração
                if (abs($novoValor - (float) $mensalidade->valor_pago) < 0.005) {
                    continue;
                }

                if (! Gate::forUser($ator)->allows('update', $mensalidade)) {
                    throw new AuthorizationException(
                        "Sem permissão para alterar a mensalidade {$mensalidade->mes}/{$mensalidade->ano}."
                    );
                }

                $mensalidade->update([
                    'valor_pago' => $novoValor,
                    'pago_em' => $novoValor > 0 ? now()->toDateString() : null,
                    'contabilizado' => Gate::forUser($ator)->allows('gerenciarContabilizado', Mensalidade::class)
                        ? $mensalidade->contabilizado
                        : true,
                ]);

                $gravadas++;
            }

            return $gravadas;
        });
    }
}
