<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\PagamentosNovo;

use App\Models\PagamentoNovo;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detalhe do pagamento no modelo novo. Recibo só via Policy emitirRecibo
 * (oculto quando estornado — DEV-09/BR-HUMANA-006).
 */
#[Layout('layouts.app')]
class Detalhe extends Component
{
    public PagamentoNovo $pagamento;

    public function mount(PagamentoNovo $pagamento): void
    {
        $pagamento->load([
            'pessoa',
            'unidade',
            'taxasCondominiais',
            'estornos',
            'estornoDe',
        ]);

        $this->pagamento = $pagamento;
    }

    public function render()
    {
        return view('livewire.financeiro.pagamentos-novo.detalhe', [
            'totalAplicado' => $this->pagamento->taxasCondominiais->sum(fn ($t) => (float) $t->pivot->valor_aplicado),
        ]);
    }
}
