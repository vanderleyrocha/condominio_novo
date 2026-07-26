<?php

declare(strict_types=1);

namespace App\Livewire\Cadastros\Pessoas;

use App\Actions\Cadastros\ExcluirPessoa;
use App\Models\Pessoa;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listagem de pessoas (modelo novo — substitui Proprietários no cutover).
 * Ordenação por nome, paginação 15, busca por nome/documento.
 */
#[Layout('layouts.app')]
#[Title('Pessoas')]
class Listagem extends Component
{
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $busca = '';

    public ?int $confirmandoExclusaoId = null;

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function confirmarExclusao(int $id): void
    {
        $this->confirmandoExclusaoId = $id;
    }

    public function cancelarExclusao(): void
    {
        $this->confirmandoExclusaoId = null;
    }

    public function excluir(ExcluirPessoa $acao): void
    {
        if ($this->confirmandoExclusaoId === null) {
            return;
        }

        $pessoa = Pessoa::query()->findOrFail($this->confirmandoExclusaoId);

        $this->authorize('delete', $pessoa);

        try {
            $acao->executar($pessoa);
            session()->flash('status', 'Pessoa removida com sucesso!');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->confirmandoExclusaoId = null;
        $this->resetPage();
    }

    public function formatarDocumento(?string $documento): string
    {
        return match (strlen($documento ?? '')) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $documento),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $documento),
            default => $documento ?? '—',
        };
    }

    public function render()
    {
        $pessoas = Pessoa::query()
            ->withCount('vinculos')
            ->when($this->busca !== '', function ($query): void {
                $digitos = preg_replace('/\D/', '', $this->busca);
                $query->where(fn ($q) => $q
                    ->where('nome', 'like', "%{$this->busca}%")
                    ->when($digitos !== '', fn ($qq) => $qq->orWhere('cpf_cnpj', 'like', "%{$digitos}%")));
            })
            ->orderBy('nome')
            ->paginate(15);

        return view('livewire.cadastros.pessoas.listagem', ['pessoas' => $pessoas]);
    }
}
