<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PagamentoNovo;
use App\Models\User;

/**
 * Pagamentos no modelo novo: admin/sindico registram (level_one aceito até o
 * remap do cutover); estorno segue restrito a admin (BR-HUMANA-004); recibo
 * bloqueado para pagamento estornado ou registro de estorno (BR-HUMANA-006).
 */
class PagamentoNovoPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSindico() || $user->isLevelOne();
    }

    public function estornar(User $user, PagamentoNovo $pagamento): bool
    {
        return $user->isAdmin();
    }

    public function emitirRecibo(User $user, PagamentoNovo $pagamento): bool
    {
        return ! $pagamento->estornos()->exists() && ! $pagamento->isEstorno();
    }
}
