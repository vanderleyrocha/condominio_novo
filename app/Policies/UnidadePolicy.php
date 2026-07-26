<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unidade;
use App\Models\User;

/**
 * Controle de acesso novo (03-modelo-dados.md): admin e sindico gerenciam
 * unidades e vínculos; proprietario não. LevelOne aceito transitoriamente
 * até o remap do cutover.
 */
class UnidadePolicy
{
    public function create(User $user): bool
    {
        return $this->podeGerir($user);
    }

    public function update(User $user, Unidade $unidade): bool
    {
        return $this->podeGerir($user);
    }

    public function delete(User $user, Unidade $unidade): bool
    {
        return $this->podeGerir($user);
    }

    public function gerirVinculos(User $user, Unidade $unidade): bool
    {
        return $this->podeGerir($user);
    }

    private function podeGerir(User $user): bool
    {
        return $user->isAdmin() || $user->isSindico() || $user->isLevelOne();
    }
}
