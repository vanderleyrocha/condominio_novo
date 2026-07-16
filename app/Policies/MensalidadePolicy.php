<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mensalidade;
use App\Models\User;
use App\Support\ParametrosCondominio;

/**
 * Reproduz a matriz de permissões do legado (permissions.md §3) via Policy:
 * admin edita tudo; level-one só quando pago_em é nulo ou posterior à data de corte.
 */
class MensalidadePolicy
{
    public function view(User $user, Mensalidade $mensalidade): bool
    {
        return true; // qualquer autenticado (paridade com o legado)
    }

    public function update(User $user, Mensalidade $mensalidade): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isLevelOne()) {
            return $mensalidade->pago_em === null
                || $mensalidade->pago_em->toDateString() > ParametrosCondominio::dataCorteLevelOne();
        }

        return false;
    }

    public function lancar(User $user): bool
    {
        return true; // rota autenticada sem gate no legado (permissions.md 🟡)
    }

    /**
     * Apenas admin define contabilizado = 0; level-one sempre grava 1 (RN-08).
     */
    public function gerenciarContabilizado(User $user): bool
    {
        return $user->isAdmin();
    }
}
