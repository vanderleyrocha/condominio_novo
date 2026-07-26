<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Índices econômicos são dados GLOBAIS (sem condominio_id) e afetam cálculos
 * de correção — restritos a admin (matriz de acesso em 03-modelo-dados.md:
 * sindico não gerencia dados globais). Paridade com IpcaPolicy do legado.
 */
class IndiceEconomicoPolicy
{
    public function gerenciar(User $user): bool
    {
        return $user->isAdmin();
    }
}
