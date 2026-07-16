<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Proprietario;
use App\Models\User;

class ProprietarioPolicy
{
    public function create(User $user): bool
    {
        return true; // paridade: CRUD aberto a autenticados no legado
    }

    public function update(User $user, Proprietario $proprietario): bool
    {
        return true;
    }

    /**
     * Exclusão bloqueada com imóveis (RN-28) ou pagamentos (Q-05) — guarda na Action;
     * aqui apenas a autorização do ator.
     */
    public function delete(User $user, Proprietario $proprietario): bool
    {
        return true;
    }
}
