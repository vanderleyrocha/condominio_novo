<?php

declare(strict_types=1);

namespace App\Actions\Acesso;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Edição do próprio perfil, com upload de foto (decisão P5 — o legado validava
 * o campo photo mas nunca persistia).
 */
class AtualizarPerfil
{
    public function executar(User $usuario, string $email, ?UploadedFile $foto = null): User
    {
        $usuario->email = $email;

        if ($foto !== null) {
            if ($usuario->foto_perfil !== null) {
                Storage::disk('public')->delete($usuario->foto_perfil);
            }
            $usuario->foto_perfil = $foto->store('perfil', 'public');
        }

        $usuario->save();

        return $usuario;
    }
}
