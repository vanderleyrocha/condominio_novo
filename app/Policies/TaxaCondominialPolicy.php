<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaxaCondominial;
use App\Models\User;

/**
 * Controle de acesso novo (03-modelo-dados.md): admin e sindico gerem taxas
 * sem a regra de data de corte do legado (aposentada com o level_one).
 * LevelOne aceito transitoriamente até o remap do cutover, equiparado a
 * sindico. `contabilizado` continua exclusivo do admin (RN-08).
 */
class TaxaCondominialPolicy
{
    public function view(User $user, TaxaCondominial $taxa): bool
    {
        return true; // portal do proprietário restringe por vínculo em fase própria
    }

    public function update(User $user, TaxaCondominial $taxa): bool
    {
        return $this->podeGerir($user);
    }

    public function lancar(User $user): bool
    {
        return $this->podeGerir($user);
    }

    public function pagarViaGrade(User $user): bool
    {
        return $this->podeGerir($user);
    }

    public function gerenciarContabilizado(User $user): bool
    {
        return $user->isAdmin();
    }

    private function podeGerir(User $user): bool
    {
        return $user->isAdmin() || $user->isSindico() || $user->isLevelOne();
    }
}
