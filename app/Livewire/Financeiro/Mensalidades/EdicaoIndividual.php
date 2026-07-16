<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Mensalidades;

use App\Actions\Financeiro\AtualizarMensalidade;
use App\Models\Mensalidade;
use App\Support\DinheiroBr;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Edição individual de mensalidade (tela mensalidades.edicao).
 * DEV-04: o recálculo jQuery do legado (edit.blade.php:35-49) vira hook reativo —
 * ao alterar acréscimo/desconto com valor pago "0,00", valor_pago = valor + acréscimo - desconto.
 */
#[Layout('layouts.app')]
class EdicaoIndividual extends Component
{
    public Mensalidade $mensalidade;

    #[Validate('required|string')]
    public string $valor = '';

    #[Validate('required|string')]
    public string $desconto = '';

    #[Validate('required|string')]
    public string $acrescimo = '';

    #[Validate('required|string')]
    public string $valorPago = '';

    #[Validate('required|date')]
    public string $vencimento = '';

    #[Validate('nullable|date')]
    public ?string $pagoEm = null;

    public bool $contabilizado = true;

    public string $erro = '';

    public function mount(Mensalidade $mensalidade): void
    {
        $this->authorize('update', $mensalidade);

        $this->mensalidade = $mensalidade;
        $this->valor = DinheiroBr::formatar($mensalidade->valor);
        $this->desconto = DinheiroBr::formatar($mensalidade->desconto);
        $this->acrescimo = DinheiroBr::formatar($mensalidade->acrescimo);
        $this->valorPago = DinheiroBr::formatar($mensalidade->valor_pago);
        $this->vencimento = $mensalidade->vencimento?->toDateString() ?? '';
        $this->pagoEm = $mensalidade->pago_em?->toDateString();
        $this->contabilizado = (bool) $mensalidade->contabilizado;
    }

    public function updated(string $property): void
    {
        // Regra do legado: alterar acréscimo/desconto com valor pago zerado recalcula o valor pago
        if (! in_array($property, ['acrescimo', 'desconto'], true) || $this->valorPago !== '0,00') {
            return;
        }

        try {
            $recalculado = (float) DinheiroBr::paraDecimal($this->valor)
                + (float) DinheiroBr::paraDecimal($this->acrescimo)
                - (float) DinheiroBr::paraDecimal($this->desconto);
        } catch (InvalidArgumentException) {
            return;
        }

        $this->valorPago = DinheiroBr::formatar($recalculado);
    }

    public function salvar(AtualizarMensalidade $acao): void
    {
        $this->erro = '';
        $this->validate();

        try {
            $this->authorize('update', $this->mensalidade);
        } catch (AuthorizationException) {
            $this->erro = 'Atualização não autorizada ou data anterior a autorização do seu usuário';

            return;
        }

        try {
            $dados = [
                'valor' => DinheiroBr::paraDecimal($this->valor),
                'desconto' => DinheiroBr::paraDecimal($this->desconto),
                'acrescimo' => DinheiroBr::paraDecimal($this->acrescimo),
                'valor_pago' => DinheiroBr::paraDecimal($this->valorPago),
                'vencimento' => $this->vencimento,
                'pago_em' => $this->pagoEm ?: null,
            ];
        } catch (InvalidArgumentException $e) {
            $this->addError('valor', 'Informe valores monetários válidos no formato 0,00.');

            return;
        }

        if (Gate::allows('gerenciarContabilizado', Mensalidade::class)) {
            $dados['contabilizado'] = $this->contabilizado;
        }

        $acao->executar($this->mensalidade, $dados, auth()->user());

        session()->flash('status', 'Atualização realizada com sucesso');

        $this->redirectRoute('mensalidades.index', [
            'ano' => $this->mensalidade->ano,
            'imovel' => $this->mensalidade->imovel_id,
        ]);
    }

    public function render()
    {
        return view('livewire.financeiro.mensalidades.edicao-individual');
    }
}
