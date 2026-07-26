<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Resumo;

use App\Models\CobrancaExtraordinaria;
use App\Models\LancamentoFinanceiro;
use App\Models\Unidade;
use App\Support\ResumoFinanceiro;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Resumo das receitas e despesas no modelo novo — matriz ano × unidades.
 * Mesma semântica do Resumo legado (EX-04): pagamentos aplicados em taxas
 * contabilizadas, agrupados pelo ano do pagamento, + receitas − despesas.
 */
#[Layout('layouts.app')]
#[Title('Resumo financeiro')]
class Index extends Component
{
    #[Url(as: 'apartir_de')]
    public string $apartirDe = '';

    public function updatedApartirDe(): void
    {
        $this->dispatch('resumo-grafico-atualizado', dados: $this->montarResumo()['graficoDados']);
    }

    /**
     * @return array<string, mixed>
     */
    private function montarResumo(): array
    {
        $temFiltro = $this->apartirDe !== '';

        $saldo = $temFiltro ? ResumoFinanceiro::saldoAte($this->apartirDe) : 0.0;

        $unidades = Unidade::query()->orderBy('identificacao')->pluck('identificacao', 'identificacao');

        $aplicacoes = ResumoFinanceiro::aplicacoesContabilizadas()
            ->join('unidades', 'unidades.id', '=', 'taxas_condominiais.unidade_id')
            ->when($temFiltro, fn ($q) => $q->where('pagamentos_novo.data_pagamento', '>', $this->apartirDe))
            ->selectRaw(ResumoFinanceiro::anoSql('pagamentos_novo.data_pagamento').' as ano, unidades.identificacao, SUM(pagamento_taxa.valor_aplicado) as total')
            ->groupBy('ano', 'unidades.identificacao')
            ->orderBy('ano')
            ->get();

        $lancamentos = LancamentoFinanceiro::query()
            ->when($temFiltro, fn ($q) => $q->where('data_lancamento', '>', $this->apartirDe))
            ->selectRaw(ResumoFinanceiro::anoSql('data_lancamento').' as ano, natureza, SUM(valor) as total')
            ->groupBy('ano', 'natureza')
            ->orderBy('ano')
            ->get();

        $resumo = [];
        $totalUnidade = [];

        foreach ($aplicacoes as $linha) {
            $resumo[$linha->ano][$linha->identificacao] = ($resumo[$linha->ano][$linha->identificacao] ?? 0) + (float) $linha->total;
            $totalUnidade[$linha->identificacao] = ($totalUnidade[$linha->identificacao] ?? 0) + (float) $linha->total;
        }

        foreach ($lancamentos as $linha) {
            $chave = $linha->natureza === 'despesa' ? 'despesas' : 'receita';
            $resumo[$linha->ano][$chave] = ($resumo[$linha->ano][$chave] ?? 0) + (float) $linha->total;
        }

        ksort($resumo);

        $totalReceita = 0.0;
        $totalDespesa = 0.0;
        $totalGeral = 0.0;

        foreach ($resumo as $dados) {
            $totalReceita += (float) ($dados['receita'] ?? 0);
            $totalDespesa += (float) ($dados['despesas'] ?? 0);
        }

        foreach ($totalUnidade as $valor) {
            $totalGeral += (float) $valor;
        }

        // Série anual para o gráfico: receitas = taxas do ano + outras receitas.
        $graficoDados = ['anos' => [], 'receitas' => [], 'despesas' => []];

        foreach ($resumo as $ano => $dados) {
            $receitasAno = (float) ($dados['receita'] ?? 0);
            foreach ($unidades as $unidade) {
                $receitasAno += (float) ($dados[$unidade] ?? 0);
            }

            $graficoDados['anos'][] = (string) $ano;
            $graficoDados['receitas'][] = round($receitasAno, 2);
            $graficoDados['despesas'][] = round((float) ($dados['despesas'] ?? 0), 2);
        }

        // Apuração de cobranças extraordinárias: pivot de taxas + receitas com origem
        $cobrancas = CobrancaExtraordinaria::query()
            ->withSum('taxasCondominiais as total_taxas', 'cobranca_extraordinaria_taxa.valor')
            ->orderBy('nome')
            ->get();

        $receitasPorCobranca = LancamentoFinanceiro::query()
            ->where('origem_type', CobrancaExtraordinaria::class)
            ->selectRaw('origem_id, COALESCE(SUM(valor), 0) as total')
            ->groupBy('origem_id')
            ->pluck('total', 'origem_id');

        return [
            'unidades' => $unidades,
            'resumo' => $resumo,
            'totalUnidade' => $totalUnidade,
            'saldo' => $saldo,
            'totalReceita' => $totalReceita,
            'totalDespesa' => $totalDespesa,
            'totalGeral' => $totalGeral,
            'totalReceitas' => $totalGeral + $totalReceita,
            'saldoFinal' => $totalGeral + $totalReceita - $totalDespesa + $saldo,
            'graficoDados' => $graficoDados,
            'cobrancas' => $cobrancas,
            'receitasPorCobranca' => $receitasPorCobranca,
        ];
    }

    public function render()
    {
        return view('livewire.financeiro.resumo.index', $this->montarResumo());
    }
}
