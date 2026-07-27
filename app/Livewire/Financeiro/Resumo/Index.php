<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Resumo;

use App\Enums\NaturezaLancamento;
use App\Models\CobrancaExtraordinaria;
use App\Models\LancamentoFinanceiro;
use App\Models\Unidade;
use App\Services\RateioPorFinalidadeService;
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

    /**
     * Sem parâmetro na URL, o resumo abre nos últimos 5 anos: 01/01 do ano
     * atual menos 5. O saldo anterior a essa data entra como "saldo anterior".
     */
    public function mount(): void
    {
        if ($this->apartirDe === '') {
            $this->apartirDe = now()->subYears(5)->startOfYear()->toDateString();
        }
    }

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
            $chave = $linha->natureza === NaturezaLancamento::Despesa ? 'despesas' : 'receita';
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

        // Apuração de cobranças extraordinárias: itens de taxa gerados pela
        // cobrança + receitas com origem nela (Etapa 6 de
        // 05-plano-composicao-taxas.md — o pivô foi descontinuado)
        $cobrancas = CobrancaExtraordinaria::query()
            ->withSum('itensTaxa as total_taxas', 'valor')
            ->orderBy('nome')
            ->get();

        $receitasPorCobranca = LancamentoFinanceiro::query()
            ->where('origem_type', CobrancaExtraordinaria::class)
            ->selectRaw('origem_id, COALESCE(SUM(valor), 0) as total')
            ->groupBy('origem_id')
            ->pluck('total', 'origem_id');

        // Recursos carimbados: o saldo das finalidades restritas está no caixa,
        // mas não pode custear despesa corrente. A leitura é sempre ACUMULADA
        // desde o início (um fundo não tem recorte de período) — e o saldo
        // final acima também é, porque inclui o saldo anterior ao filtro.
        // O disponível sai por SUBTRAÇÃO do saldo final, nunca por uma soma
        // paralela: assim os dois números não têm como divergir.
        $rateio = app(RateioPorFinalidadeService::class);
        $vinculadas = $rateio->vinculadoPorFinalidade();
        $saldoVinculado = (float) $rateio->somarSaldoVinculado($vinculadas);

        $saldoFinal = $totalGeral + $totalReceita - $totalDespesa + $saldo;

        return [
            'unidades' => $unidades,
            'resumo' => $resumo,
            'totalUnidade' => $totalUnidade,
            'saldo' => $saldo,
            'totalReceita' => $totalReceita,
            'totalDespesa' => $totalDespesa,
            'totalGeral' => $totalGeral,
            'totalReceitas' => $totalGeral + $totalReceita,
            'saldoFinal' => $saldoFinal,
            'vinculadas' => $vinculadas,
            'saldoVinculado' => $saldoVinculado,
            'disponivelCusteio' => $saldoFinal - $saldoVinculado,
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
