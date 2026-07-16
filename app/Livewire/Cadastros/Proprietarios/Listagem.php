<?php

declare(strict_types=1);

namespace App\Livewire\Cadastros\Proprietarios;

use App\Actions\Cadastros\ExcluirProprietario;
use App\Models\Proprietario;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listagem de proprietários (paridade: ordenação por nome ASC, paginação 15).
 * Exclusão via modal de confirmação (DEV-13) — mesma mensagem literal do legado.
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    use WithPagination;

    public ?int $confirmandoExclusaoId = null;

    public function confirmarExclusao(int $id): void
    {
        $this->confirmandoExclusaoId = $id;
    }

    public function cancelarExclusao(): void
    {
        $this->confirmandoExclusaoId = null;
    }

    public function excluir(ExcluirProprietario $acao): void
    {
        if ($this->confirmandoExclusaoId === null) {
            return;
        }

        $proprietario = Proprietario::query()->findOrFail($this->confirmandoExclusaoId);

        $this->authorize('delete', $proprietario);

        try {
            $acao->executar($proprietario);
            session()->flash('status', 'Proprietário removido com sucesso!');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->confirmandoExclusaoId = null;
        $this->resetPage();
    }

    public function formatarCpf(?string $cpf): string
    {
        if ($cpf === null || strlen($cpf) !== 11) {
            return $cpf ?? '-';
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($cpf, 0, 3),
            substr($cpf, 3, 3),
            substr($cpf, 6, 3),
            substr($cpf, 9, 2),
        );
    }

    public function render()
    {
        return view('livewire.cadastros.proprietarios.listagem', [
            'proprietarios' => Proprietario::query()->orderBy('nome')->paginate(15),
        ]);
    }
}
