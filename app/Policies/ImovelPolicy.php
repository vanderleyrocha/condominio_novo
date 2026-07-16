<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Imovel;
use App\Models\User;

/**
 * CRUD de imóveis é novo (BR-HUMANA-002) — restrito a admin.
 */
class ImovelPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Imovel $imovel): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Imovel $imovel): bool
    {
        return $user->isAdmin();
    }
}
