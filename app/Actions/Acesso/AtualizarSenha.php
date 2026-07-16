<?php

declare(strict_types=1);

namespace App\Actions\Acesso;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Troca de senha do próprio usuário: exige a senha atual e nova diferente
 * (paridade com CurrentPasswordCheckRule do legado — BR-MIGRAR-016).
 */
class AtualizarSenha
{
    public function executar(User $usuario, string $senhaAtual, string $novaSenha): void
    {
        if (! Hash::check($senhaAtual, $usuario->password)) {
            throw new DomainException('A senha atual informada não confere.');
        }

        if (Hash::check($novaSenha, $usuario->password)) {
            throw new DomainException('A nova senha deve ser diferente da senha atual.');
        }

        $usuario->update(['password' => $novaSenha]);
    }
}
