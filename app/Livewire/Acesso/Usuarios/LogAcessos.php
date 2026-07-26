<?php

declare(strict_types=1);

namespace App\Livewire\Acesso\Usuarios;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Log de acessos: últimos 3 acessos por usuário (paridade com o legado),
 * renderizado com agrupamento válido por usuário (DEV-14).
 */
#[Layout('layouts.app')]
#[Title('Log de acessos')]
class LogAcessos extends Component
{
    public function render()
    {
        return view('livewire.acesso.usuarios.log-acessos', [
            'usuarios' => User::query()
                ->with(['accesses' => fn ($query) => $query->orderByDesc('datetime')->limit(3)])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
