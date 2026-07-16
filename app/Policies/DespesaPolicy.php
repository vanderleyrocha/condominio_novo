<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Despesa;
use App\Models\User;
use App\Support\ParametrosCondominio;

/**
 * Paridade com DespesaController do legado (permissions.md §4):
 * admin sempre; level-one apenas quando a data da despesa é posterior à data de corte.
 */
class DespesaPolicy
{
    public function create(User $user, ?string $data = null): bool
    {
        return $this->autorizadoParaData($user, $data);
    }

    public function update(User $user, Despesa $despesa, ?string $data = null): bool
    {
        return $this->autorizadoParaData($user, $data ?? $despesa->data->toDateString());
    }

    public function gerenciarContabilizado(User $user): bool
    {
        return $user->isAdmin();
    }

    private function autorizadoParaData(User $user, ?string $data): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isLevelOne()) {
            return $data !== null && $data > ParametrosCondominio::dataCorteLevelOne();
        }

        return false;
    }
}
