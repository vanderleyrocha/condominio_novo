<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Pagamentos;

use App\Models\Pagamento;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listagem de pagamentos (tela pagamentos.listagem): data DESC com paginação.
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    use WithPagination;

    public function render()
    {
        $pagamentos = Pagamento::query()
            ->with(['proprietario', 'imovel'])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.financeiro.pagamentos.listagem', [
            'pagamentos' => $pagamentos,
        ]);
    }
}
