<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Resumo;

use App\Models\CobrancaExtra;
use App\Models\Despesa;
use App\Models\Imovel;
use App\Models\Mensalidade;
use App\Models\Receita;
use App\Support\ResumoFinanceiro;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Resumo das receitas e despesas — matriz ano × imóveis.
 *
 * Reproduz o resumo do PageController legado já corrigido (EX-04):
 * saldo = mensalidades contabilizadas (ano de referência = pago_em, SEM
 * orWhereNull) + receitas − despesas. A apuração de cobranças extras
 * substitui a poupança/juros hardcoded do legado.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    #[Url(as: 'apartir_de')]
    public string $apartirDe = '';

    public function render()
    {
        $temFiltro = $this->apartirDe !== '';

        $saldo = $temFiltro ? ResumoFinanceiro::saldoAte($this->apartirDe) : 0.0;

        $imoveis = Imovel::query()->orderBy('nome')->pluck('nome', 'nome');

        // EX-04: ano de referência = pago_em, apenas mensalidades pagas (sem orWhereNull)
        $mensalidades = Mensalidade::query()
            ->with('imovel')
            ->where('contabilizado', true)
            ->whereNotNull('pago_em')
            ->when($temFiltro, fn ($q) => $q->where('pago_em', '>', $this->apartirDe))
            ->orderBy('pago_em')
            ->get();

        $despesas = Despesa::query()
            ->when($temFiltro, fn ($q) => $q->where('data', '>', $this->apartirDe))
            ->orderBy('data')
            ->get();

        $receitas = Receita::query()
            ->when($temFiltro, fn ($q) => $q->where('data', '>', $this->apartirDe))
            ->orderBy('data')
            ->get();

        $resumo = [];
        $totalImovel = [];

        foreach ($mensalidades as $mensalidade) {
            $ano = $mensalidade->pago_em->year;
            $nome = $mensalidade->imovel->nome;

            $resumo[$ano][$nome] = ($resumo[$ano][$nome] ?? 0) + (float) $mensalidade->valor_pago;
            $totalImovel[$nome] = ($totalImovel[$nome] ?? 0) + (float) $mensalidade->valor_pago;
        }

        foreach ($despesas as $despesa) {
            $ano = $despesa->data->year;
            $resumo[$ano]['despesas'] = ($resumo[$ano]['despesas'] ?? 0) + (float) $despesa->valor;
        }

        foreach ($receitas as $receita) {
            $ano = $receita->data->year;
            $resumo[$ano]['receita'] = ($resumo[$ano]['receita'] ?? 0) + (float) $receita->valor;
        }

        ksort($resumo);

        $totalReceita = 0.0;
        $totalDespesa = 0.0;
        $totalGeral = 0.0;

        foreach ($resumo as $dados) {
            $totalReceita += (float) ($dados['receita'] ?? 0);
            $totalDespesa += (float) ($dados['despesas'] ?? 0);
        }

        foreach ($totalImovel as $valor) {
            $totalGeral += (float) $valor;
        }

        // Apuração de cobranças extras: pivots + receitas vinculadas
        $cobrancas = CobrancaExtra::query()
            ->withSum('receitas as total_receitas', 'valor')
            ->withSum('mensalidades as total_mensalidades', 'cobranca_extra_mensalidade.valor')
            ->orderBy('nome')
            ->get();

        return view('livewire.financeiro.resumo.index', [
            'imoveis' => $imoveis,
            'resumo' => $resumo,
            'totalImovel' => $totalImovel,
            'saldo' => $saldo,
            'totalReceita' => $totalReceita,
            'totalDespesa' => $totalDespesa,
            'totalGeral' => $totalGeral,
            'cobrancas' => $cobrancas,
        ]);
    }
}
