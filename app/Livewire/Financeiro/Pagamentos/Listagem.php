<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Pagamentos;

use App\Models\Pagamento;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listagem de pagamentos no modelo novo: data DESC com paginação.
 */
#[Layout('layouts.app')]
#[Title('Pagamentos')]
class Listagem extends Component
{
    use WithPagination;

    public function render()
    {
        $pagamentos = Pagamento::query()
            ->with(['pessoa', 'unidade', 'estornos'])
            ->orderByDesc('data_pagamento')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.financeiro.pagamentos.listagem', [
            'pagamentos' => $pagamentos,
        ]);
    }
}
