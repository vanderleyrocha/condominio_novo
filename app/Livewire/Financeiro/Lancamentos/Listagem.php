<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Lancamentos;

use App\Actions\Financeiro\SalvarLancamento;
use App\Enums\NaturezaLancamento;
use App\Enums\TipoPlanoConta;
use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use App\Models\LancamentoFinanceiro;
use App\Models\PlanoConta;
use App\Support\DinheiroBr;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lançamentos financeiros (modelo novo — substitui as telas de Receitas e
 * Despesas no cutover): tabela unificada com filtro por natureza, ano, mês,
 * plano de contas e descrição. Criação/edição inline; sem exclusão (RN-29).
 */
#[Layout('layouts.app')]
#[Title('Lançamentos')]
class Listagem extends Component
{
    use WithPagination;

    #[Url]
    public string $natureza = '';

    #[Url]
    public string $ano = '';

    #[Url]
    public string $mes = '';

    #[Url]
    public string $descricao = '';

    #[Url]
    public int $plano = 0;

    public bool $formAberto = false;

    public ?int $lancamentoId = null;

    public string $formNatureza = 'despesa';

    #[Validate('required|integer|exists:planos_contas,id', as: 'Plano de contas')]
    public ?int $formPlanoId = null;

    #[Validate('required|date', as: 'Data')]
    public string $formData = '';

    #[Validate('required|string|max:255', as: 'Descrição')]
    public string $formDescricao = '';

    #[Validate('required|string', as: 'Valor')]
    public string $formValor = '0,00';

    public bool $formContabilizado = false;

    #[Validate('nullable|integer|exists:cobrancas_extraordinarias,id', as: 'Cobrança extraordinária')]
    public ?int $formCobrancaId = null;

    public string $novoPlano = '';

    public string $mensagem = '';

    public function mount(): void
    {
        if ($this->ano === '') {
            $this->ano = date('Y');
        }
    }

    public function updated(string $propriedade): void
    {
        if (in_array($propriedade, ['natureza', 'ano', 'mes', 'descricao', 'plano'], true)) {
            $this->resetPage();
        }

        // Trocar a natureza do formulário reseta o plano (planos são por tipo)
        if ($propriedade === 'formNatureza') {
            $this->formPlanoId = null;
            $this->formCobrancaId = null;
        }
    }

    public function novoLancamento(): void
    {
        $this->authorize('create', LancamentoFinanceiro::class);

        $this->resetErrorBag();
        $this->lancamentoId = null;
        $this->formNatureza = 'despesa';
        $this->formPlanoId = null;
        $this->formData = date('Y-m-d');
        $this->formDescricao = '';
        $this->formValor = '0,00';
        $this->formContabilizado = false;
        $this->formCobrancaId = null;
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function editar(int $id): void
    {
        $lancamento = LancamentoFinanceiro::query()->findOrFail($id);

        $this->authorize('update', $lancamento);

        $this->resetErrorBag();
        $this->lancamentoId = $lancamento->id;
        $this->formNatureza = $lancamento->natureza->value;
        $this->formPlanoId = $lancamento->plano_conta_id;
        $this->formData = $lancamento->data_lancamento->toDateString();
        $this->formDescricao = $lancamento->descricao;
        $this->formValor = DinheiroBr::formatar($lancamento->valor);
        $this->formContabilizado = (bool) $lancamento->contabilizado;
        $this->formCobrancaId = $lancamento->origem_id !== null ? (int) $lancamento->origem_id : null;
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function cancelar(): void
    {
        $this->formAberto = false;
        $this->resetErrorBag();
    }

    /**
     * Criação rápida de plano de contas na natureza do formulário
     * (paridade com o "novo tipo" da tela de despesas do legado).
     */
    public function criarPlano(): void
    {
        $this->authorize('create', LancamentoFinanceiro::class);

        $descricao = trim($this->novoPlano);

        if ($descricao === '') {
            $this->addError('novoPlano', 'Informe a descrição do plano.');

            return;
        }

        $condominioId = (int) Condominio::query()->value('id');
        $tipo = TipoPlanoConta::from($this->formNatureza);
        $prefixo = $tipo === TipoPlanoConta::Despesa ? 'D' : 'R';

        $plano = PlanoConta::query()->firstOrCreate(
            ['condominio_id' => $condominioId, 'descricao' => $descricao, 'tipo' => $tipo->value],
            ['codigo' => sprintf('%s-%03d', $prefixo, PlanoConta::query()->where('tipo', $tipo->value)->count() + 1)],
        );

        $this->formPlanoId = $plano->id;
        $this->novoPlano = '';
        $this->resetErrorBag('novoPlano');
    }

    public function salvar(SalvarLancamento $acao): void
    {
        $lancamento = $this->lancamentoId !== null
            ? LancamentoFinanceiro::query()->findOrFail($this->lancamentoId)
            : null;

        $this->authorize($lancamento === null ? 'create' : 'update', $lancamento ?? LancamentoFinanceiro::class);
        $this->validate();

        try {
            $valor = DinheiroBr::paraDecimal($this->formValor);
        } catch (\InvalidArgumentException) {
            $this->addError('formValor', 'Valor monetário inválido.');

            return;
        }

        $acao->executar([
            'plano_conta_id' => (int) $this->formPlanoId,
            'natureza' => $this->formNatureza,
            'data' => $this->formData,
            'descricao' => $this->formDescricao,
            'valor' => $valor,
            'contabilizado' => $this->formContabilizado,
            'cobranca_extraordinaria_id' => $this->formNatureza === 'receita' ? $this->formCobrancaId : null,
        ], $lancamento);

        $this->formAberto = false;
        $this->mensagem = $lancamento === null
            ? 'Lançamento gravado com sucesso'
            : 'Alteração realizada com sucesso';
    }

    public function render()
    {
        $query = LancamentoFinanceiro::query()
            ->with(['planoConta', 'origem'])
            ->when($this->natureza !== '', fn ($q) => $q->where('natureza', $this->natureza))
            ->when($this->ano !== '', fn ($q) => $q->whereYear('data_lancamento', $this->ano))
            ->when($this->mes !== '', fn ($q) => $q->whereMonth('data_lancamento', $this->mes))
            ->when($this->descricao !== '', fn ($q) => $q->where('descricao', 'like', '%'.$this->descricao.'%'))
            ->when($this->plano !== 0, fn ($q) => $q->where('plano_conta_id', $this->plano));

        $totalReceitas = (string) ((clone $query)->where('natureza', 'receita')->sum('valor') ?: '0');
        $totalDespesas = (string) ((clone $query)->where('natureza', 'despesa')->sum('valor') ?: '0');

        return view('livewire.financeiro.lancamentos.listagem', [
            'lancamentos' => $query->orderBy('data_lancamento', 'DESC')->orderBy('id', 'DESC')->paginate(15),
            'totalReceitas' => $totalReceitas,
            'totalDespesas' => $totalDespesas,
            'planos' => PlanoConta::query()->orderBy('tipo')->orderBy('codigo')->get(),
            'planosDoForm' => PlanoConta::query()->where('tipo', $this->formNatureza)->orderBy('codigo')->get(),
            'cobrancas' => CobrancaExtraordinaria::query()->orderBy('nome')->get(['id', 'nome']),
            'naturezas' => NaturezaLancamento::cases(),
        ]);
    }
}
