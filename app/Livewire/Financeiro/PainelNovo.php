<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro;

use App\Models\LancamentoFinanceiro;
use App\Models\PagamentoNovo;
use App\Models\TaxaCondominial;
use App\Support\ResumoFinanceiroNovo;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Painel inicial no modelo novo (substitui PainelInicial e a rota `/` no
 * cutover) — mesmos agregados via ResumoFinanceiroNovo.
 */
#[Layout('layouts.app')]
class PainelNovo extends Component
{
    public function render()
    {
        $inicioMes = now()->startOfMonth()->toDateString();
        $fimMes = now()->endOfMonth()->toDateString();

        $saldoAnterior = ResumoFinanceiroNovo::saldoAnterior($inicioMes);
        $totaisMes = ResumoFinanceiroNovo::totaisEntre($inicioMes, $fimMes);

        $movimentoMes = $totaisMes['taxas'] + $totaisMes['receitas'] - $totaisMes['despesas'];
        $saldoMes = $saldoAnterior + $movimentoMes;

        $emAbertoQuantidade = TaxaCondominial::query()->emAberto()->count();

        $emAbertoTotal = (float) TaxaCondominial::query()
            ->emAberto()
            ->selectRaw('COALESCE(SUM(COALESCE(valor_original, 0) - COALESCE(valor_desconto, 0)
                - COALESCE((SELECT SUM(valor_aplicado) FROM pagamento_taxa
                    WHERE pagamento_taxa.taxa_condominial_id = taxas_condominiais.id), 0)), 0) as total')
            ->value('total');

        $ultimaReceita = LancamentoFinanceiro::query()->where('natureza', 'receita')
            ->orderByDesc('data_lancamento')->orderByDesc('id')->first();
        $ultimaDespesa = LancamentoFinanceiro::query()->where('natureza', 'despesa')
            ->orderByDesc('data_lancamento')->orderByDesc('id')->first();

        $ultimosPagamentos = PagamentoNovo::query()
            ->with(['pessoa', 'unidade'])
            ->orderByDesc('data_pagamento')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('livewire.financeiro.painel-novo', [
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
