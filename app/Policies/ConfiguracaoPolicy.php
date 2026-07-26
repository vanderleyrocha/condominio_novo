<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Configurações são escopadas por condomínio (03-modelo-dados.md) — admin e
 * sindico gerenciam. No legado a tela de parâmetros era admin-only; sindico
 * ganha o acesso porque parâmetros do condomínio fazem parte da sua gestão.
 */
class ConfiguracaoPolicy
{
    public function gerenciar(User $user): bool
    {
        return $user->isAdmin() || $user->isSindico();
    }
}
