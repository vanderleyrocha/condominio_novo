<?php

declare(strict_types=1);

namespace App\Livewire\Acesso\Usuarios;

use App\Actions\Acesso\ExcluirUsuario;
use App\Actions\Acesso\SalvarUsuario;
use App\Enums\PapelUsuario;
use App\Models\User;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Gestão de usuários — funcionalidade nova (decisão P4), admin-only via UserPolicy.
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    public bool $exibirFormulario = false;

    public ?int $usuarioId = null;

    public string $name = '';

    public string $email = '';

    public string $papel = 'level_one';

    public string $password = '';

    public string $password_confirmation = '';

    public ?int $confirmandoExclusaoId = null;

    public function mount(): void
    {
        $this->authorize('gerenciar', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'name')->ignore($this->usuarioId),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->usuarioId),
            ],
            'papel' => ['required', Rule::enum(PapelUsuario::class)],
            // Senha obrigatória na criação; opcional na edição (validada só se preenchida)
            'password' => $this->usuarioId !== null && $this->password === ''
                ? ['nullable']
                : ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function novo(): void
    {
        $this->authorize('gerenciar', User::class);

        $this->reset('usuarioId', 'name', 'email', 'password', 'password_confirmation');
        $this->papel = 'level_one';
        $this->resetErrorBag();
        $this->exibirFormulario = true;
    }

    public function editar(int $id): void
    {
        $usuario = User::query()->findOrFail($id);

        $this->authorize('update', $usuario);

        $this->usuarioId = $usuario->id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->papel = $usuario->papel->value;
        $this->reset('password', 'password_confirmation');
        $this->resetErrorBag();
        $this->exibirFormulario = true;
    }

    public function cancelar(): void
    {
        $this->reset('exibirFormulario', 'usuarioId', 'name', 'email', 'password', 'password_confirmation');
        $this->resetErrorBag();
    }

    public function salvar(SalvarUsuario $acao): void
    {
        $usuario = $this->usuarioId !== null
            ? User::query()->findOrFail($this->usuarioId)
            : null;

        $this->authorize($usuario === null ? 'gerenciar' : 'update', $usuario ?? User::class);

        $dados = $this->validate();

        try {
            $acao->executar([
                'name' => $dados['name'],
                'email' => $dados['email'],
                'papel' => PapelUsuario::from($dados['papel']),
                'password' => $this->password !== '' ? $this->password : null,
            ], $usuario);

            session()->flash('status', $usuario === null
                ? 'Usuário cadastrado com sucesso!'
                : 'Usuário atualizado com sucesso!');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->cancelar();
    }

    public function confirmarExclusao(int $id): void
    {
        $this->confirmandoExclusaoId = $id;
    }

    public function cancelarExclusao(): void
    {
        $this->confirmandoExclusaoId = null;
    }

    public function excluir(ExcluirUsuario $acao): void
    {
        if ($this->confirmandoExclusaoId === null) {
            return;
        }

        $usuario = User::query()->findOrFail($this->confirmandoExclusaoId);

        $this->authorize('delete', $usuario);

        try {
            $acao->executar($usuario, auth()->user());
            session()->flash('status', 'Usuário removido com sucesso!');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->confirmandoExclusaoId = null;
    }

    public function render()
    {
        return view('livewire.acesso.usuarios.listagem', [
            'usuarios' => User::query()
                ->withMax('accesses as ultimo_acesso', 'datetime')
                ->orderBy('name')
                ->get(),
            'papeis' => PapelUsuario::cases(),
        ]);
    }
}
