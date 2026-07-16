<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * CRUD de índices IPCA é novo (BR-HUMANA-003) — restrito a admin.
 */
class IpcaPolicy
{
    public function gerenciar(User $user): bool
    {
        return $user->isAdmin();
    }
}
