<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro;

use App\Models\Despesa;
use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Models\Receita;
use App\Support\ResumoFinanceiro;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Painel inicial — dashboard com o resumo financeiro real (DA-10, DEV-01),
 * usando as mesmas queries do Resumo via App\Support\ResumoFinanceiro.
 */
#[Layout('layouts.app')]
class PainelInicial extends Component
{
    public function render()
    {
        $inicioMes = now()->startOfMonth()->toDateString();
        $fimMes = now()->endOfMonth()->toDateString();

        $saldoAnterior = ResumoFinanceiro::saldoAnterior($inicioMes);
        $totaisMes = ResumoFinanceiro::totaisEntre($inicioMes, $fimMes);

        $movimentoMes = $totaisMes['mensalidades'] + $totaisMes['receitas'] - $totaisMes['despesas'];
        $saldoMes = $saldoAnterior + $movimentoMes;

        $emAbertoQuantidade = Mensalidade::query()->emAberto()->count();

        $emAbertoTotal = (float) Mensalidade::query()
            ->emAberto()
            ->selectRaw('COALESCE(SUM(COALESCE(valor, 0) - COALESCE(desconto, 0) - COALESCE(valor_pago, 0)), 0) as total')
            ->value('total');

        $ultimaReceita = Receita::query()->orderByDesc('data')->orderByDesc('id')->first();
        $ultimaDespesa = Despesa::query()->orderByDesc('data')->orderByDesc('id')->first();

        $ultimosPagamentos = Pagamento::query()
            ->with(['proprietario', 'imovel'])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('livewire.financeiro.painel-inicial', [
            'saldoMes' => $saldoMes,
            'saldoAnterior' => $saldoAnterior,
            'movimentoMes' => $movimentoMes,
            'emAbertoQuantidade' => $emAbertoQuantidade,
            'emAbertoTotal' => $emAbertoTotal,
            'ultimaReceita' => $ultimaReceita,
            'ultimaDespesa' => $ultimaDespesa,
            'ultimosPagamentos' => $ultimosPagamentos,
        ]);
    }
}
