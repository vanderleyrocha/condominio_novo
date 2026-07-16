<?php

declare(strict_types=1);

namespace App\Actions\Acesso;

use App\Models\User;
use DomainException;

class ExcluirUsuario
{
    public function executar(User $usuario, User $ator): void
    {
        if ($usuario->id === $ator->id) {
            throw new DomainException('Você não pode excluir o próprio usuário.');
        }

        $usuario->delete();
    }
}
