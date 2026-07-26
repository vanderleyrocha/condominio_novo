<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Pagamentos;

use App\Actions\Financeiro\RegistrarPagamento;
use App\Enums\FormaPagamento;
use App\Models\Pagamento;
use App\Models\Pessoa;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Support\ConfiguracoesCondominio;
use App\Support\DinheiroBr;
use DomainException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Registro de pagamento no modelo novo. Diferença estrutural: seleção de
 * PESSOA + UNIDADE (uma pessoa pode ter vínculo com várias unidades) e
 * forma de pagamento (inexistente no legado). Simulação FIFO reativa
 * idêntica ao legado; persistência via RegistrarPagamento.
 */
#[Layout('layouts.app')]
class Registro extends Component
{
    #[Validate('required|date')]
    public string $data = '';

    #[Validate('required|string|max:255')]
    public string $descricao = '';

    #[Validate('required|string')]
    public string $valor = '';

    #[Validate('required|integer|exists:pessoas,id')]
    public ?int $pessoaId = null;

    #[Validate('required|integer|exists:unidades,id')]
    public ?int $unidadeId = null;

    public string $forma = 'nao_informado';

    /** @var list<int|string> anos marcados no filtro */
    public array $anos = [];

    public bool $todosAnos = false;

    /** @var list<int|string> ids das taxas selecionadas */
    public array $selecionadas = [];

    public string $erro = '';

    public function mount(): void
    {
        $this->authorize('create', Pagamento::class);

        $this->data = now()->toDateString();
    }

    /** @return list<int> */
    public function anosDisponiveis(): array
    {
        return range(ConfiguracoesCondominio::anoInicialFiltroPagamentos(), (int) now()->format('Y'));
    }

    public function updatedPessoaId(): void
    {
        $this->selecionadas = [];
        $unidades = $this->unidadesDaPessoa();
        $this->unidadeId = $unidades->count() === 1 ? $unidades->first()->id : null;
    }

    public function updatedUnidadeId(): void
    {
        $this->selecionadas = [];
    }

    public function updatedTodosAnos(bool $marcado): void
    {
        $this->anos = $marcado ? $this->anosDisponiveis() : [];
        $this->selecionadas = [];
    }

    public function updatedAnos(): void
    {
        $this->todosAnos = count($this->anos) === count($this->anosDisponiveis());
        $this->selecionadas = [];
    }

    #[Computed]
    public function pessoas(): Collection
    {
        return Pessoa::query()->orderBy('nome')->get();
    }

    #[Computed]
    public function unidadesDaPessoa(): Collection
    {
        if ($this->pessoaId === null) {
            return collect();
        }

        return Unidade::query()
            ->whereHas('vinculos', fn ($q) => $q->where('pessoa_id', $this->pessoaId)->whereNull('data_fim'))
            ->orderBy('identificacao')
            ->get();
    }

    /**
     * Taxas com saldo devedor na unidade selecionada, ordem cronológica —
     * mesma regra do legado: devido > pago.
     */
    #[Computed]
    public function taxasEmAberto(): Collection
    {
        if ($this->unidadeId === null || $this->anos === []) {
            return collect();
        }

        return TaxaCondominial::query()
            ->where('unidade_id', $this->unidadeId)
            ->whereRaw('(COALESCE(valor_original, 0) + COALESCE(valor_acrescimo, 0) - COALESCE(valor_desconto, 0))
                > COALESCE((SELECT SUM(valor_aplicado) FROM pagamento_taxa
                    WHERE pagamento_taxa.taxa_condominial_id = taxas_condominiais.id), 0)')
            ->whereIn('competencia_ano', array_map('intval', $this->anos))
            ->withSum('pagamentoTaxas as valor_pago', 'valor_aplicado')
            ->orderBy('competencia_ano')
            ->orderBy('competencia_mes')
            ->get();
    }

    /**
     * Simulação FIFO (mesma regra da Action): min(devido restante, saldo).
     *
     * @return array{linhas: array<int, float>, saldo: float}
     */
    #[Computed]
    public function alocacao(): array
    {
        try {
            $saldo = (float) DinheiroBr::paraDecimal($this->valor);
        } catch (InvalidArgumentException) {
            $saldo = 0.0;
        }

        $selecionadas = array_map('intval', $this->selecionadas);
        $linhas = [];

        foreach ($this->taxasEmAberto() as $taxa) {
            if (! in_array($taxa->id, $selecionadas, true)) {
                continue;
            }

            $devido = (float) $taxa->valorDevido() - (float) ($taxa->valor_pago ?? 0);
            $aplicado = max(min($devido, $saldo), 0.0);
            $linhas[$taxa->id] = $aplicado;
            $saldo -= $aplicado;
        }

        return ['linhas' => $linhas, 'saldo' => $saldo];
    }

    public function salvar(RegistrarPagamento $acao): void
    {
        $this->erro = '';
        $this->validate();

        try {
            $valor = (float) DinheiroBr::paraDecimal($this->valor);
        } catch (InvalidArgumentException) {
            $this->addError('valor', 'Informe um valor monetário válido.');

            return;
        }

        try {
            $pagamento = $acao->executar(
                Pessoa::query()->findOrFail($this->pessoaId),
                Unidade::query()->findOrFail($this->unidadeId),
                $this->data,
                $this->descricao,
                $valor,
                array_map('intval', $this->selecionadas),
                FormaPagamento::from($this->forma),
            );
        } catch (DomainException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        session()->flash('status', 'Pagamento registrado com sucesso.');

        $this->redirectRoute('pagamentos.show', ['pagamento' => $pagamento]);
    }

    public function render()
    {
        return view('livewire.financeiro.pagamentos.registro', [
            'formas' => FormaPagamento::cases(),
        ]);
    }
}
