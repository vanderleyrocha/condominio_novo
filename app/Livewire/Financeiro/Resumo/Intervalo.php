<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Resumo;

use App\Models\Despesa;
use App\Models\Mensalidade;
use App\Models\Receita;
use App\Support\ResumoFinanceiro;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Resumo por intervalo — mensalidades pagas no período + receitas + despesas
 * e saldo, com defaults no primeiro/último dia do mês corrente.
 */
#[Layout('layouts.app')]
class Intervalo extends Component
{
    #[Url]
    public string $de = '';

    #[Url]
    public string $ate = '';

    public function mount(): void
    {
        if ($this->de === '') {
            $this->de = date('Y-m-01');
        }

        if ($this->ate === '') {
            $this->ate = date('Y-m-t');
        }
    }

    public function render()
    {
        $saldo = ResumoFinanceiro::saldoAnterior($this->de);

        $mensalidades = Mensalidade::query()
            ->with('imovel.proprietario')
            ->where('contabilizado', true)
            ->whereNotNull('pago_em')
            ->whereBetween('pago_em', [$this->de, $this->ate])
            ->orderBy('pago_em')
            ->get();

        $receitas = Receita::query()
            ->whereBetween('data', [$this->de, $this->ate])
            ->orderBy('data')
            ->get();

        $despesas = Despesa::query()
            ->whereBetween('data', [$this->de, $this->ate])
            ->orderBy('data')
            ->get();

        $totalReceita = (float) $mensalidades->sum('valor_pago') + (float) $receitas->sum('valor');
        $totalDespesa = (float) $despesas->sum('valor');

        return view('livewire.financeiro.resumo.intervalo', [
            'saldo' => $saldo,
            'mensalidades' => $mensalidades,
            'receitas' => $receitas,
            'despesas' => $despesas,
            'totalReceita' => $totalReceita,
            'totalDespesa' => $totalDespesa,
            'saldoFinal' => $saldo + $totalReceita - $totalDespesa,
        ]);
    }
}
