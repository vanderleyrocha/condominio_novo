<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pagamento;
use App\Models\User;

/**
 * Pagamentos no modelo novo: admin/sindico registram (level_one aceito até o
 * remap do cutover); estorno segue restrito a admin (BR-HUMANA-004); recibo
 * bloqueado para pagamento estornado ou registro de estorno (BR-HUMANA-006).
 */
class PagamentoPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSindico();
    }

    public function estornar(User $user, Pagamento $pagamento): bool
    {
        return $user->isAdmin();
    }

    public function emitirRecibo(User $user, Pagamento $pagamento): bool
    {
        return ! $pagamento->estornos()->exists() && ! $pagamento->isEstorno();
    }
}
