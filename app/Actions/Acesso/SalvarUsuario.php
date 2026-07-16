<?php

declare(strict_types=1);

namespace App\Actions\Acesso;

use App\Enums\PapelUsuario;
use App\Models\User;

/**
 * CRUD de usuários — funcionalidade nova (decisão P4), admin-only via UserPolicy.
 */
class SalvarUsuario
{
    /**
     * @param array{name: string, email: string, papel: PapelUsuario, password?: string|null} $dados
     */
    public function executar(array $dados, ?User $usuario = null): User
    {
        $atributos = [
            'name' => $dados['name'],
            'email' => $dados['email'],
            'papel' => $dados['papel'],
        ];

        if (! empty($dados['password'])) {
            $atributos['password'] = $dados['password'];
        }

        if ($usuario === null) {
            return User::query()->create($atributos);
        }

        $usuario->update($atributos);

        return $usuario;
    }
}
