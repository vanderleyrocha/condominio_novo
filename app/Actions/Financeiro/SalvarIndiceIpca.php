<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Ipca;

/**
 * CRUD de índices IPCA — novo (BR-HUMANA-003); unique (ano, mes) no schema.
 */
class SalvarIndiceIpca
{
    public function executar(int $ano, int $mes, string $indice, ?Ipca $ipca = null): Ipca
    {
        $dados = ['ano' => $ano, 'mes' => $mes, 'indice' => $indice];

        if ($ipca === null) {
            return Ipca::query()->create($dados);
        }

        $ipca->update($dados);

        return $ipca;
    }
}
