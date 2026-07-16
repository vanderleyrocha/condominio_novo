<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Dividas;

use App\Models\Mensalidade;
use App\Services\CorrecaoMonetariaService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dívidas — read model: mensalidades em aberto agrupadas por imóvel e ano,
 * com valor corrigido pelo IPCA (data-base hoje). Paridade com DividaController::index.
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    public function render(CorrecaoMonetariaService $correcao)
    {
        $mensalidades = Mensalidade::query()
            ->with('imovel')
            ->emAberto()
            ->orderBy('imovel_id')
            ->orderBy('vencimento')
            ->get();

        $dividas = [];
        $anos = [];
        $imoveis = [];

        foreach ($mensalidades as $mensalidade) {
            $ano = $mensalidade->vencimento->year;
            $nome = $mensalidade->imovel->nome;

            $valorCorrigido = $correcao->corrigirMensalidade(
                (float) $mensalidade->valor_liquido,
                $mensalidade->vencimento,
            );

            $dividas[$nome][$ano] = ($dividas[$nome][$ano] ?? 0) + $valorCorrigido;
            $anos[$ano] = $ano;
            $imoveis[$nome] = $mensalidade->imovel;
        }

        ksort($anos);

        $totalAno = [];
        $totalGeral = 0.0;
        $totalImovel = [];

        foreach ($dividas as $nome => $porAno) {
            foreach ($porAno as $ano => $valor) {
                $totalAno[$ano] = ($totalAno[$ano] ?? 0) + $valor;
                $totalImovel[$nome] = ($totalImovel[$nome] ?? 0) + $valor;
                $totalGeral += $valor;
            }
        }

        return view('livewire.financeiro.dividas.listagem', [
            'dividas' => $dividas,
            'anos' => $anos,
            'imoveis' => $imoveis,
            'totalAno' => $totalAno,
            'totalImovel' => $totalImovel,
            'totalGeral' => $totalGeral,
        ]);
    }
}
