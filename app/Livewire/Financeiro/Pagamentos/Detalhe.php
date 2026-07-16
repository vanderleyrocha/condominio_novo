<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Pagamentos;

use App\Models\Pagamento;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detalhe do pagamento (tela pagamentos.detalhe).
 * DEV-09: botão de recibo só via Policy emitirRecibo (oculto quando estornado).
 */
#[Layout('layouts.app')]
class Detalhe extends Component
{
    public Pagamento $pagamento;

    public function mount(Pagamento $pagamento): void
    {
        $pagamento->load([
            'proprietario',
            'imovel',
            'mensalidades',
            'estornos',
            'pagamentoOrigem',
        ]);

        $this->pagamento = $pagamento;
    }

    public function render()
    {
        return view('livewire.financeiro.pagamentos.detalhe', [
            'totalAplicado' => $this->pagamento->mensalidades->sum(fn ($m) => (float) $m->pivot->valor),
        ]);
    }
}
