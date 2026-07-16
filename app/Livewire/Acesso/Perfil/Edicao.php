<?php

declare(strict_types=1);

namespace App\Livewire\Acesso\Perfil;

use App\Actions\Acesso\AtualizarPerfil;
use App\Actions\Acesso\AtualizarSenha;
use App\Models\User;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Edição do próprio perfil (tela perfil.edicao): email + foto via AtualizarPerfil,
 * troca de senha em seção separada via AtualizarSenha (BR-MIGRAR-016).
 * Pendência: requer `php artisan storage:link` para exibir a foto (disk public).
 */
#[Layout('layouts.app')]
class Edicao extends Component
{
    use WithFileUploads;

    public string $email = '';

    public mixed $foto = null;

    public string $senha_atual = '';

    public string $nova_senha = '';

    public string $nova_senha_confirmation = '';

    public function mount(): void
    {
        $this->email = $this->usuario()->email;
    }

    public function gravar(AtualizarPerfil $acao): void
    {
        $usuario = $this->usuario();

        $this->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'foto' => ['nullable', 'image', 'max:2048'],
        ]);

        $acao->executar($usuario, $this->email, $this->foto);

        $this->foto = null;

        session()->flash('status', 'Perfil atualizado com sucesso!');

        $this->redirectRoute('perfil.edit', navigate: true);
    }

    public function alterarSenha(AtualizarSenha $acao): void
    {
        $this->validate([
            'senha_atual' => ['required', 'string'],
            'nova_senha' => ['required', 'string', 'min:6', 'confirmed', 'different:senha_atual'],
        ]);

        try {
            $acao->executar($this->usuario(), $this->senha_atual, $this->nova_senha);
        } catch (DomainException $e) {
            $campo = str_contains($e->getMessage(), 'nova senha') ? 'nova_senha' : 'senha_atual';
            $this->addError($campo, $e->getMessage());

            return;
        }

        $this->reset('senha_atual', 'nova_senha', 'nova_senha_confirmation');

        session()->flash('status', 'Senha alterada com sucesso!');

        $this->redirectRoute('perfil.edit', navigate: true);
    }

    private function usuario(): User
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return $usuario;
    }

    public function render()
    {
        return view('livewire.acesso.perfil.edicao', [
            'usuario' => $this->usuario(),
        ]);
    }
}
