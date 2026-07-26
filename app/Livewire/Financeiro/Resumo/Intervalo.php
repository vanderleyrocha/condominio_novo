<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Resumo;

use App\Models\LancamentoFinanceiro;
use App\Support\ResumoFinanceiro;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Resumo por intervalo no modelo novo — pagamentos aplicados em taxas
 * contabilizadas no período + lançamentos, com saldo anterior.
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

        $aplicacoes = ResumoFinanceiro::aplicacoesContabilizadas()
            ->join('unidades', 'unidades.id', '=', 'taxas_condominiais.unidade_id')
            ->whereBetween('pagamentos_novo.data_pagamento', [$this->de, $this->ate])
            ->select([
                'pagamento_taxa.valor_aplicado',
                'pagamentos_novo.data_pagamento',
                'pagamentos_novo.descricao as pagamento_descricao',
                'unidades.identificacao',
                'taxas_condominiais.competencia_mes',
                'taxas_condominiais.competencia_ano',
            ])
            ->orderBy('pagamentos_novo.data_pagamento')
            ->get();

        $receitas = LancamentoFinanceiro::query()
            ->where('natureza', 'receita')
            ->whereBetween('data_lancamento', [$this->de, $this->ate])
            ->orderBy('data_lancamento')
            ->get();

        $despesas = LancamentoFinanceiro::query()
            ->with('planoConta')
            ->where('natureza', 'despesa')
            ->whereBetween('data_lancamento', [$this->de, $this->ate])
            ->orderBy('data_lancamento')
            ->get();

        $totalReceita = (float) $aplicacoes->sum('valor_aplicado') + (float) $receitas->sum('valor');
        $totalDespesa = (float) $despesas->sum('valor');

        return view('livewire.financeiro.resumo.intervalo', [
            'saldo' => $saldo,
            'aplicacoes' => $aplicacoes,
            'receitas' => $receitas,
            'despesas' => $despesas,
            'totalReceita' => $totalReceita,
            'totalDespesa' => $totalDespesa,
            'saldoFinal' => $saldo + $totalReceita - $totalDespesa,
        ]);
    }
}
