<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pessoa;
use App\Models\User;

/**
 * Controle de acesso novo (03-modelo-dados.md): admin e sindico gerenciam
 * cadastros; proprietario não. LevelOne é aceito transitoriamente até o remap
 * do cutover (level_one → sindico) — comportamento igual ao legado, onde o
 * CRUD de proprietários era aberto a autenticados.
 */
class PessoaPolicy
{
    public function create(User $user): bool
    {
        return $this->podeGerir($user);
    }

    public function update(User $user, Pessoa $pessoa): bool
    {
        return $this->podeGerir($user);
    }

    public function delete(User $user, Pessoa $pessoa): bool
    {
        return $this->podeGerir($user);
    }

    private function podeGerir(User $user): bool
    {
        return $user->isAdmin() || $user->isSindico() || $user->isLevelOne();
    }
}
