<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ItemTaxa;
use App\Models\User;

/**
 * Item de taxa é a composição do valor devido — alterá-lo altera a cobrança,
 * então a alçada é a mesma de atualizar a taxa (TaxaCondominialPolicy::update).
 */
class ItemTaxaPolicy
{
    public function view(User $user, ItemTaxa $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->podeGerir($user);
    }

    public function update(User $user, ItemTaxa $item): bool
    {
        return $this->podeGerir($user);
    }

    public function delete(User $user, ItemTaxa $item): bool
    {
        return $this->podeGerir($user);
    }

    private function podeGerir(User $user): bool
    {
        return $user->isAdmin() || $user->isSindico();
    }
}
