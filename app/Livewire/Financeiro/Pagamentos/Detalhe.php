<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Pagamentos;

use App\Models\Pagamento;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Detalhe do pagamento no modelo novo. Recibo só via Policy emitirRecibo
 * (oculto quando estornado — DEV-09/BR-HUMANA-006).
 */
#[Layout('layouts.app')]
#[Title('Detalhe do pagamento')]
class Detalhe extends Component
{
    public Pagamento $pagamento;

    public function mount(Pagamento $pagamento): void
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
        return view('livewire.financeiro.pagamentos.detalhe', [
            'totalAplicado' => $this->pagamento->taxasCondominiais->sum(fn ($t) => (float) $t->pivot->valor_aplicado),
        ]);
    }
}
