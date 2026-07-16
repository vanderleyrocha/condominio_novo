<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Mensalidades;

use App\Actions\Financeiro\LancarMensalidades;
use App\Models\Mensalidade;
use App\Support\DinheiroBr;
use App\Support\ParametrosCondominio;
use DomainException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Lançamento em lote de mensalidades do ano (tela mensalidades.lancamento).
 * Corrige DEV-05 do legado (form postava para rota inexistente): persistência
 * via Action LancarMensalidades, nunca direto no banco.
 */
#[Layout('layouts.app')]
class Lancamento extends Component
{
    #[Validate('required|integer|min:2000|max:2100')]
    public int $ano;

    #[Validate('required|string')]
    public string $valor = '';

    public string $erro = '';

    public function mount(): void
    {
        $this->authorize('lancar', Mensalidade::class);

        $this->ano = (int) now()->format('Y');
        $this->valor = DinheiroBr::formatar(ParametrosCondominio::taxaMensalidadePadrao());
    }

    public function lancar(LancarMensalidades $acao): void
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

        session()->flash('status', "{$quantidade} mensalidades lançadas com sucesso para o ano {$this->ano}.");

        $this->redirectRoute('mensalidades.index', ['ano' => $this->ano]);
    }

    public function render()
    {
        return view('livewire.financeiro.mensalidades.lancamento');
    }
}
