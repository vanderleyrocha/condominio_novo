<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Gestão de usuários é nova (decisão P4) — restrita a admin.
 * Perfil próprio: qualquer usuário edita apenas o próprio (paridade com o legado).
 */
class UserPolicy
{
    public function gerenciar(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $alvo): bool
    {
        return $user->isAdmin() || $user->id === $alvo->id;
    }

    public function delete(User $user, User $alvo): bool
    {
        return $user->isAdmin() && $user->id !== $alvo->id;
    }
}
