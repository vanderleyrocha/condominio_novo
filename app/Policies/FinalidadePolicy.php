<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Finalidade;
use App\Models\User;

/**
 * Finalidades definem a destinação da arrecadação — mesma alçada das taxas
 * (admin e síndico gerem; ver TaxaCondominialPolicy).
 */
class FinalidadePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Finalidade $finalidade): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->podeGerir($user);
    }

    public function update(User $user, Finalidade $finalidade): bool
    {
        return $this->podeGerir($user);
    }

    public function delete(User $user, Finalidade $finalidade): bool
    {
        return $user->isAdmin();
    }

    private function podeGerir(User $user): bool
    {
        return $user->isAdmin() || $user->isSindico();
    }
}
