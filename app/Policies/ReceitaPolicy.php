<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Receitas: qualquer autenticado cria/edita (paridade); contabilizado admin-only (RN-08).
 */
class ReceitaPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user): bool
    {
        return true;
    }

    public function gerenciarContabilizado(User $user): bool
    {
        return $user->isAdmin();
    }
}
