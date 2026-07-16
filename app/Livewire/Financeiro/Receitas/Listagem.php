<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Receitas;

use App\Actions\Financeiro\SalvarReceita;
use App\Models\CobrancaExtra;
use App\Models\Receita;
use App\Support\DinheiroBr;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Receitas — listagem com filtros ano/mês/descrição e criação/edição inline
 * via Action SalvarReceita. Sem exclusão (paridade RN-29).
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    use WithPagination;

    #[Url]
    public string $ano = '';

    #[Url]
    public string $mes = '';

    #[Url]
    public string $descricao = '';

    public bool $formAberto = false;

    public ?int $receitaId = null;

    #[Validate('required|date', as: 'Data')]
    public string $formData = '';

    #[Validate('required|string|max:255', as: 'Descrição')]
    public string $formDescricao = '';

    #[Validate('required|string', as: 'Valor')]
    public string $formValor = '0,00';

    public bool $formContabilizado = false;

    #[Validate('nullable|integer|exists:cobrancas_extras,id', as: 'Cobrança extra')]
    public ?int $formCobrancaExtraId = null;

    public string $mensagem = '';

    public function mount(): void
    {
        if ($this->ano === '') {
            $this->ano = date('Y');
        }
    }

    public function updated(string $propriedade): void
    {
        if (in_array($propriedade, ['ano', 'mes', 'descricao'], true)) {
            $this->resetPage();
        }
    }

    public function novaReceita(): void
    {
        $this->resetErrorBag();
        $this->receitaId = null;
        $this->formData = date('Y-m-d');
        $this->formDescricao = '';
        $this->formValor = '0,00';
        $this->formContabilizado = false;
        $this->formCobrancaExtraId = null;
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function editar(int $id): void
    {
        $receita = Receita::query()->findOrFail($id);

        $this->resetErrorBag();
        $this->receitaId = $receita->id;
        $this->formData = $receita->data->toDateString();
        $this->formDescricao = $receita->descricao;
        $this->formValor = DinheiroBr::formatar($receita->valor);
        $this->formContabilizado = (bool) $receita->contabilizado;
        $this->formCobrancaExtraId = $receita->cobranca_extra_id;
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function cancelar(): void
    {
        $this->formAberto = false;
        $this->resetErrorBag();
    }

    public function salvar(SalvarReceita $acao): void
    {
        $this->validate();

        try {
            $valor = DinheiroBr::paraDecimal($this->formValor);
        } catch (\InvalidArgumentException) {
            $this->addError('formValor', 'Valor monetário inválido.');

            return;
        }

        $receita = $this->receitaId !== null
            ? Receita::query()->findOrFail($this->receitaId)
            : null;

        $acao->executar([
            'data' => $this->formData,
            'descricao' => $this->formDescricao,
            'valor' => $valor,
            'contabilizado' => $this->formContabilizado,
            'cobranca_extra_id' => $this->formCobrancaExtraId,
        ], auth()->user(), $receita);

        $this->formAberto = false;
        $this->mensagem = $receita === null
            ? 'Receita gravada com sucesso'
            : 'Alteração realizada com sucesso';
    }

    public function render()
    {
        $query = Receita::query()
            ->with('cobrancaExtra')
            ->when($this->ano !== '', fn ($q) => $q->whereYear('data', $this->ano))
            ->when($this->mes !== '', fn ($q) => $q->whereMonth('data', $this->mes))
            ->when($this->descricao !== '', fn ($q) => $q->where('descricao', 'like', '%'.$this->descricao.'%'));

        $total = (clone $query)->sum('valor');

        return view('livewire.financeiro.receitas.listagem', [
            'receitas' => $query->orderBy('data', 'DESC')->paginate(15),
            'total' => $total,
            'cobrancasExtras' => CobrancaExtra::query()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }
}
