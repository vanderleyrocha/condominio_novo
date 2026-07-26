<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Taxas;

use App\Actions\Financeiro\AtualizarTaxa;
use App\Models\TaxaCondominial;
use App\Support\DinheiroBr;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Edição individual de taxa (modelo novo). Edita o VALOR DEVIDO (original,
 * desconto, acréscimo, vencimento, contabilizado); o valor pago é derivado de
 * pagamento_taxa e aparece somente para leitura — pagamentos entram pelo
 * módulo de pagamentos ou pela grade anual.
 */
#[Layout('layouts.app')]
#[Title('Edição de taxa')]
class EdicaoIndividual extends Component
{
    public TaxaCondominial $taxa;

    #[Validate('required|string')]
    public string $valorOriginal = '';

    #[Validate('required|string')]
    public string $desconto = '';

    #[Validate('required|string')]
    public string $acrescimo = '';

    #[Validate('required|date')]
    public string $vencimento = '';

    public bool $contabilizado = true;

    public string $erro = '';

    public function mount(TaxaCondominial $taxa): void
    {
        $this->authorize('update', $taxa);

        $this->taxa = $taxa;
        $this->valorOriginal = DinheiroBr::formatar($taxa->valor_original);
        $this->desconto = DinheiroBr::formatar($taxa->valor_desconto);
        $this->acrescimo = DinheiroBr::formatar($taxa->valor_acrescimo);
        $this->vencimento = $taxa->vencimento?->toDateString() ?? '';
        $this->contabilizado = (bool) $taxa->contabilizado;
    }

    public function salvar(AtualizarTaxa $acao): void
    {
        $this->erro = '';
        $this->validate();
        $this->authorize('update', $this->taxa);

        try {
            $dados = [
                'valor_original' => DinheiroBr::paraDecimal($this->valorOriginal),
                'valor_desconto' => DinheiroBr::paraDecimal($this->desconto),
                'valor_acrescimo' => DinheiroBr::paraDecimal($this->acrescimo),
                'vencimento' => $this->vencimento,
            ];
        } catch (InvalidArgumentException) {
            $this->addError('valorOriginal', 'Informe valores monetários válidos no formato 0,00.');

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

    public function render()
    {
        return view('livewire.financeiro.taxas.edicao-individual', [
            'valorPago' => (string) ($this->taxa->pagamentoTaxas()->sum('valor_aplicado') ?: '0'),
        ]);
    }
}
