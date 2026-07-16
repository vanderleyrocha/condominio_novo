<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pagamento;
use App\Models\User;

class PagamentoPolicy
{
    public function create(User $user): bool
    {
        return true; // paridade: rota autenticada sem gate no legado
    }

    /**
     * Estorno restrito a admin (decisão BR-HUMANA-004).
     */
    public function estornar(User $user, Pagamento $pagamento): bool
    {
        return $user->isAdmin();
    }

    /**
     * Recibo bloqueado para pagamento estornado (BR-HUMANA-006 / DA-09).
     */
    public function emitirRecibo(User $user, Pagamento $pagamento): bool
    {
        return ! $pagamento->estornado && ! $pagamento->isEstorno();
    }
}
