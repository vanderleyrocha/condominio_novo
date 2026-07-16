<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Dividas;

use App\Models\Imovel;
use App\Models\Mensalidade;
use App\Services\CorrecaoMonetariaService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dívidas por imóvel — grade Jan–Dez por ano com valor corrigido (IPCA,
 * data-base hoje) e memória de cálculo expansível.
 */
#[Layout('layouts.app')]
class PorImovel extends Component
{
    public Imovel $imovel;

    /** @var array<int, bool> memória de cálculo aberta por ano */
    public array $memoriaAberta = [];

    public function mount(Imovel $imovel): void
    {
        $this->imovel = $imovel->load('proprietario');
    }

    public function alternarMemoria(int $ano): void
    {
        $this->memoriaAberta[$ano] = ! ($this->memoriaAberta[$ano] ?? false);
    }

    public function render(CorrecaoMonetariaService $correcao)
    {
        $mensalidades = Mensalidade::query()
            ->where('imovel_id', $this->imovel->id)
            ->emAberto()
            ->orderBy('vencimento')
            ->get();

        $porAno = $mensalidades->groupBy(fn (Mensalidade $m) => $m->vencimento->year)->sortKeys();

        $linhas = [];
        $grade = [];
        $memoria = [];
        $total = 0.0;
        $totalCorrigido = 0.0;

        foreach ($porAno as $ano => $grupo) {
            $valorAno = 0.0;
            $corrigidoAno = 0.0;

            $grade[$ano] = array_fill(1, 12, null);

            foreach ($grupo as $mensalidade) {
                $valorLiquido = (float) $mensalidade->valor_liquido;
                $valorCorrigido = $correcao->corrigirMensalidade($valorLiquido, $mensalidade->vencimento);

                $valorAno += $valorLiquido;
                $corrigidoAno += $valorCorrigido;

                $mes = $mensalidade->vencimento->month;
                $grade[$ano][$mes] = ($grade[$ano][$mes] ?? 0) + $valorCorrigido;

                $memoria[$ano][] = $correcao->memoriaCalculo($valorLiquido, $mensalidade->vencimento) + [
                    'competencia' => $mensalidade->vencimento->format('m/Y'),
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

        return view('livewire.financeiro.dividas.por-imovel', [
            'linhas' => $linhas,
            'grade' => $grade,
            'memoria' => $memoria,
            'total' => $total,
            'totalCorrigido' => $totalCorrigido,
        ]);
    }
}
