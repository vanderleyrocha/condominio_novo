<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Mensalidade;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Edição individual de mensalidade (BR-MIGRAR-002 / RN-06..RN-10).
 * Autorização por Policy (quem pode) acontece no componente; aqui vivem as
 * regras de consistência do dado, idênticas ao legado:
 *  - valor_pago == 0  => pago_em = null (RN-09)
 *  - valor_pago > 0 sem data => pago_em = hoje (RN-10)
 *  - contabilizado só muda para quem pode gerenciá-lo; level-one grava sempre 1 (RN-08)
 */
class AtualizarMensalidade
{
    /**
     * @param array{valor: string, desconto: string, acrescimo: string, valor_pago: string, pago_em: ?string, vencimento: string, contabilizado?: bool} $dados
     */
    public function executar(Mensalidade $mensalidade, array $dados, User $ator): Mensalidade
    {
        $valorPago = (float) $dados['valor_pago'];

        if ($valorPago == 0.0) {
            $dados['pago_em'] = null;
        } elseif (empty($dados['pago_em'])) {
            $dados['pago_em'] = now()->toDateString();
        }

        if (! Gate::forUser($ator)->allows('gerenciarContabilizado', Mensalidade::class)) {
            $dados['contabilizado'] = true;
        }

        $mensalidade->update($dados);

        return $mensalidade;
    }
}
