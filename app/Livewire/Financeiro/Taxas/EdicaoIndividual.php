<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Taxas;

use App\Actions\Financeiro\AtualizarTaxa;
use App\Actions\Financeiro\RemoverItemTaxa;
use App\Actions\Financeiro\SalvarItemTaxa;
use App\Enums\TipoPlanoConta;
use App\Models\Finalidade;
use App\Models\PlanoConta;
use App\Models\TaxaCondominial;
use App\Support\DinheiroBr;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Edição individual de taxa (modelo novo). Etapa 5 de
 * docs/migration/05-plano-composicao-taxas.md: o valor devido NÃO é mais um
 * campo — é a soma dos ITENS da composição, editados aqui mesmo. O formulário
 * de cima cuida do que continua sendo da taxa (vencimento, desconto, acréscimo,
 * contabilizado); o valor pago é derivado de pagamento_taxa e só aparece para
 * leitura.
 */
#[Layout('layouts.app')]
#[Title('Edição de taxa')]
class EdicaoIndividual extends Component
{
    public TaxaCondominial $taxa;

    #[Validate('required|string')]
    public string $desconto = '';

    #[Validate('required|string')]
    public string $acrescimo = '';

    #[Validate('required|date')]
    public string $vencimento = '';

    public bool $contabilizado = true;

    public string $erro = '';

    // --- Composição (itens) ---

    public string $mensagemComposicao = '';

    public bool $itemAberto = false;

    public ?int $itemId = null;

    #[Validate('required|string|max:255', as: 'Descrição')]
    public string $itemDescricao = '';

    #[Validate('required|string', as: 'Valor')]
    public string $itemValor = '0,00';

    #[Validate('required|integer|exists:planos_contas,id', as: 'Plano de contas')]
    public ?int $itemPlanoContaId = null;

    #[Validate('nullable|integer|exists:finalidades,id', as: 'Finalidade')]
    public ?int $itemFinalidadeId = null;

    public function mount(TaxaCondominial $taxa): void
    {
        $this->authorize('update', $taxa);

        $this->taxa = $taxa;
        $this->desconto = DinheiroBr::formatar($taxa->valor_desconto);
        $this->acrescimo = DinheiroBr::formatar($taxa->valor_acrescimo);
        $this->vencimento = $taxa->vencimento?->toDateString() ?? '';
        $this->contabilizado = (bool) $taxa->contabilizado;
    }

    public function salvar(AtualizarTaxa $acao): void
    {
        $this->erro = '';
        $this->validateOnly('desconto');
        $this->validateOnly('acrescimo');
        $this->validateOnly('vencimento');
        $this->authorize('update', $this->taxa);

        try {
            $dados = [
                'valor_desconto' => DinheiroBr::paraDecimal($this->desconto),
                'valor_acrescimo' => DinheiroBr::paraDecimal($this->acrescimo),
                'vencimento' => $this->vencimento,
            ];
        } catch (InvalidArgumentException) {
            $this->addError('desconto', 'Informe valores monetários válidos no formato 0,00.');

            return;
        }

        if (Gate::allows('gerenciarContabilizado', TaxaCondominial::class)) {
            $dados['contabilizado'] = $this->contabilizado;
        }

        $acao->executar($this->taxa, $dados, auth()->user());

        session()->flash('status', 'Atualização realizada com sucesso');

        $this->redirectRoute('taxas.index', [
            'ano' => $this->taxa->competencia_ano,
            'unidade' => $this->taxa->unidade_id,
        ]);
    }

    public function novoItem(): void
    {
        $this->resetErrorBag();
        $this->erro = '';
        $this->itemId = null;
        $this->itemDescricao = '';
        $this->itemValor = '0,00';
        $this->itemPlanoContaId = (int) $this->planosReceita()->keys()->first();
        $this->itemFinalidadeId = null;
        $this->itemAberto = true;
    }

    public function editarItem(int $id): void
    {
        $item = $this->taxa->itens()->findOrFail($id);

        $this->resetErrorBag();
        $this->erro = '';
        $this->itemId = $item->id;
        $this->itemDescricao = $item->descricao;
        $this->itemValor = DinheiroBr::formatar($item->valor);
        $this->itemPlanoContaId = $item->plano_conta_id;
        $this->itemFinalidadeId = $item->finalidade_id;
        $this->itemAberto = true;
    }

    public function cancelarItem(): void
    {
        $this->itemAberto = false;
        $this->resetErrorBag();
    }

    public function salvarItem(SalvarItemTaxa $acao): void
    {
        $this->erro = '';
        $this->validateOnly('itemDescricao');
        $this->validateOnly('itemValor');
        $this->validateOnly('itemPlanoContaId');
        $this->validateOnly('itemFinalidadeId');

        try {
            $valor = DinheiroBr::paraDecimal($this->itemValor);
        } catch (InvalidArgumentException) {
            $this->addError('itemValor', 'Informe um valor monetário válido no formato 0,00.');

            return;
        }

        $item = $this->itemId === null ? null : $this->taxa->itens()->findOrFail($this->itemId);

        try {
            $acao->executar($this->taxa, [
                'plano_conta_id' => (int) $this->itemPlanoContaId,
                'finalidade_id' => $this->itemFinalidadeId,
                'descricao' => $this->itemDescricao,
                'valor' => $valor,
            ], auth()->user(), $item);
        } catch (AuthorizationException|DomainException $e) {
            $this->erro = $e->getMessage();

            return;
        } catch (UniqueConstraintViolationException) {
            $this->addError('itemDescricao', 'Já existe um item com esta descrição nesta competência.');

            return;
        }

        $this->taxa->refresh();
        $this->itemAberto = false;
        $this->mensagemComposicao = 'Composição atualizada.';
    }

    public function removerItem(int $id, RemoverItemTaxa $acao): void
    {
        $this->erro = '';
        $this->mensagemComposicao = '';
        $item = $this->taxa->itens()->findOrFail($id);

        try {
            $acao->executar($item, auth()->user());
        } catch (AuthorizationException|DomainException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->taxa->refresh();
        $this->mensagemComposicao = 'Item removido da composição.';
    }

    public function render()
    {
        $this->taxa->load('itens.finalidade');

        return view('livewire.financeiro.taxas.edicao-individual', [
            'valorPago' => (string) ($this->taxa->pagamentoTaxas()->sum('valor_aplicado') ?: '0'),
            'itens' => $this->taxa->itens,
            'planos' => $this->planosReceita(),
            'finalidades' => Finalidade::query()->ativas()->orderBy('nome')->pluck('nome', 'id'),
            // Remover a última linha deixaria a taxa sem composição (§3.4)
            'podeRemoverItem' => $this->taxa->itens->count() > 1,
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function planosReceita(): Collection
    {
        return PlanoConta::query()
            ->where('tipo', TipoPlanoConta::Receita)
            ->orderBy('codigo')
            ->get()
            ->mapWithKeys(fn (PlanoConta $p): array => [$p->id => "{$p->codigo} — {$p->descricao}"]);
    }
}
