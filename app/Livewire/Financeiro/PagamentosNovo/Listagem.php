<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\PagamentosNovo;

use App\Models\PagamentoNovo;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listagem de pagamentos no modelo novo: data DESC com paginação.
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    use WithPagination;

    public function render()
    {
        $pagamentos = PagamentoNovo::query()
            ->with(['pessoa', 'unidade', 'estornos'])
            ->orderByDesc('data_pagamento')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.financeiro.pagamentos-novo.listagem', [
            'pagamentos' => $pagamentos,
        ]);
    }
}
