<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Ipca;

use App\Actions\Financeiro\SalvarIndiceIpca;
use App\Models\Ipca;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestão de índices IPCA — CRUD novo (BR-HUMANA-003), restrito a admin.
 * Exclusão de índice permitida (novo CRUD).
 */
#[Layout('layouts.app')]
class Gestao extends Component
{
    use WithPagination;

    public bool $formAberto = false;

    public ?int $ipcaId = null;

    #[Validate('required|integer|min:1994|max:2100', as: 'Ano')]
    public ?int $formAno = null;

    #[Validate('required|integer|min:1|max:12', as: 'Mês')]
    public ?int $formMes = null;

    #[Validate('required|string', as: 'Índice')]
    public string $formIndice = '';

    public string $mensagem = '';

    public function mount(): void
    {
        $this->authorize('gerenciar', Ipca::class);
    }

    public function novoIndice(): void
    {
        $this->resetErrorBag();
        $this->ipcaId = null;
        $this->formAno = (int) date('Y');
        $this->formMes = (int) date('n');
        $this->formIndice = '';
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function editar(int $id): void
    {
        $ipca = Ipca::query()->findOrFail($id);

        $this->resetErrorBag();
        $this->ipcaId = $ipca->id;
        $this->formAno = (int) $ipca->ano;
        $this->formMes = (int) $ipca->mes;
        $this->formIndice = number_format((float) $ipca->indice, 4, ',', '');
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function cancelar(): void
    {
        $this->formAberto = false;
        $this->resetErrorBag();
    }

    public function salvar(SalvarIndiceIpca $acao): void
    {
        $this->authorize('gerenciar', Ipca::class);
        $this->validate();

        $indice = str_replace(',', '.', trim($this->formIndice));

        if (! is_numeric($indice)) {
            $this->addError('formIndice', 'Índice inválido. Informe um valor numérico, ex.: 0,53.');

            return;
        }

        $ipca = $this->ipcaId !== null
            ? Ipca::query()->findOrFail($this->ipcaId)
            : null;

        try {
            $acao->executar((int) $this->formAno, (int) $this->formMes, $indice, $ipca);
        } catch (UniqueConstraintViolationException) {
            $this->addError('formMes', 'Já existe um índice cadastrado para este mês/ano.');

            return;
        }

        $this->formAberto = false;
        $this->mensagem = $ipca === null
            ? 'Índice cadastrado com sucesso.'
            : 'Índice atualizado com sucesso.';
    }

    public function excluir(int $id): void
    {
        $this->authorize('gerenciar', Ipca::class);

        Ipca::query()->findOrFail($id)->delete();

        $this->mensagem = 'Índice excluído com sucesso.';
    }

    public function render()
    {
        return view('livewire.financeiro.ipca.gestao', [
            'indices' => Ipca::query()
                ->orderByDesc('ano')
                ->orderByDesc('mes')
                ->paginate(24),
        ]);
    }
}
