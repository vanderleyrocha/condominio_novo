<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Inadimplencia;

use App\Models\TaxaCondominial;
use App\Services\CorrecaoMonetariaNovaService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Inadimplência (modelo novo — substitui Dívidas no cutover): taxas em aberto
 * agrupadas por unidade e ano, com valor corrigido pelo índice (data-base hoje).
 * Mesma lógica de DividaController::index do legado.
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    public function render(CorrecaoMonetariaNovaService $correcao)
    {
        $taxas = TaxaCondominial::query()
            ->with('unidade')
            ->emAberto()
            ->orderBy('unidade_id')
            ->orderBy('vencimento')
            ->get();

        $dividas = [];
        $anos = [];
        $unidades = [];

        foreach ($taxas as $taxa) {
            $ano = $taxa->vencimento->year;
            $identificacao = $taxa->unidade->identificacao;

            $valorCorrigido = $correcao->corrigirTaxa(
                (float) $taxa->valor_liquido,
                $taxa->vencimento,
            );

            $dividas[$identificacao][$ano] = ($dividas[$identificacao][$ano] ?? 0) + $valorCorrigido;
            $anos[$ano] = $ano;
            $unidades[$identificacao] = $taxa->unidade;
        }

        ksort($anos);

        $totalAno = [];
        $totalGeral = 0.0;
        $totalUnidade = [];

        foreach ($dividas as $identificacao => $porAno) {
            foreach ($porAno as $ano => $valor) {
                $totalAno[$ano] = ($totalAno[$ano] ?? 0) + $valor;
                $totalUnidade[$identificacao] = ($totalUnidade[$identificacao] ?? 0) + $valor;
                $totalGeral += $valor;
            }
        }

        return view('livewire.financeiro.inadimplencia.listagem', [
            'dividas' => $dividas,
            'anos' => $anos,
            'unidades' => $unidades,
            'totalAno' => $totalAno,
            'totalUnidade' => $totalUnidade,
            'totalGeral' => $totalGeral,
        ]);
    }
}
