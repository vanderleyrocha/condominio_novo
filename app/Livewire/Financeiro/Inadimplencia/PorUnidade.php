<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Inadimplencia;

use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Services\CorrecaoMonetariaNovaService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Inadimplência por unidade (modelo novo — substitui Dívidas por imóvel):
 * grade Jan–Dez por ano com valor corrigido e memória de cálculo expansível.
 */
#[Layout('layouts.app')]
class PorUnidade extends Component
{
    public Unidade $unidade;

    /** @var array<int, bool> memória de cálculo aberta por ano */
    public array $memoriaAberta = [];

    public function mount(Unidade $unidade): void
    {
        $this->unidade = $unidade->load([
            'vinculos' => fn ($q) => $q->whereNull('data_fim')->orderByDesc('responsavel_financeiro')->with('pessoa'),
        ]);
    }

    public function alternarMemoria(int $ano): void
    {
        $this->memoriaAberta[$ano] = ! ($this->memoriaAberta[$ano] ?? false);
    }

    public function render(CorrecaoMonetariaNovaService $correcao)
    {
        $taxas = TaxaCondominial::query()
            ->where('unidade_id', $this->unidade->id)
            ->emAberto()
            ->orderBy('vencimento')
            ->get();

        $porAno = $taxas->groupBy(fn (TaxaCondominial $t) => $t->vencimento->year)->sortKeys();

        $linhas = [];
        $grade = [];
        $memoria = [];
        $total = 0.0;
        $totalCorrigido = 0.0;

        foreach ($porAno as $ano => $grupo) {
            $valorAno = 0.0;
            $corrigidoAno = 0.0;

            $grade[$ano] = array_fill(1, 12, null);

            foreach ($grupo as $taxa) {
                $valorLiquido = (float) $taxa->valor_liquido;
                $valorCorrigido = $correcao->corrigirTaxa($valorLiquido, $taxa->vencimento);

                $valorAno += $valorLiquido;
                $corrigidoAno += $valorCorrigido;

                $mes = $taxa->vencimento->month;
                $grade[$ano][$mes] = ($grade[$ano][$mes] ?? 0) + $valorCorrigido;

                $memoria[$ano][] = $correcao->memoriaCalculo($valorLiquido, $taxa->vencimento) + [
                    'competencia' => $taxa->vencimento->format('m/Y'),
                ];
            }

            $linhas[] = [
                'ano' => $ano,
                'valor' => $valorAno,
                'valor_corrigido' => $corrigidoAno,
            ];

            $total += $valorAno;
            $totalCorrigido += $corrigidoAno;
        }

        return view('livewire.financeiro.inadimplencia.por-unidade', [
            'linhas' => $linhas,
            'grade' => $grade,
            'memoria' => $memoria,
            'total' => $total,
            'totalCorrigido' => $totalCorrigido,
        ]);
    }
}
