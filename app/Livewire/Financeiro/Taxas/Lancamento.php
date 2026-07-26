<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Taxas;

use App\Actions\Financeiro\LancarTaxas;
use App\Models\TaxaCondominial;
use App\Support\ConfiguracoesCondominio;
use App\Support\DinheiroBr;
use DomainException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Lançamento em lote de taxas do ano (modelo novo — substitui o lançamento
 * de mensalidades no cutover). Persistência via Action LancarTaxas.
 */
#[Layout('layouts.app')]
#[Title('Lançamento de taxas')]
class Lancamento extends Component
{
    #[Validate('required|integer|min:2000|max:2100')]
    public int $ano;

    #[Validate('required|string')]
    public string $valor = '';

    public string $erro = '';

    public function mount(): void
    {
        $this->authorize('lancar', TaxaCondominial::class);

        $this->ano = (int) now()->format('Y');
        $this->valor = DinheiroBr::formatar(ConfiguracoesCondominio::taxaMensalidadePadrao());
    }

    public function lancar(LancarTaxas $acao): void
    {
        $this->erro = '';
        $this->validate();

        try {
            $valorDecimal = DinheiroBr::paraDecimal($this->valor);
        } catch (InvalidArgumentException) {
            $this->addError('valor', 'Informe um valor monetário válido.');

            return;
        }

        try {
            $quantidade = $acao->executar($this->ano, $valorDecimal);
        } catch (DomainException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        session()->flash('status', "{$quantidade} taxas lançadas com sucesso para o ano {$this->ano}.");

        $this->redirectRoute('taxas.index', ['ano' => $this->ano]);
    }

    public function render()
    {
        return view('livewire.financeiro.taxas.lancamento');
    }
}
