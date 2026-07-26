<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Enums\TipoIndiceEconomico;
use App\Models\IndiceEconomico;

/**
 * CRUD de índices econômicos no modelo novo (substitui SalvarIndiceIpca no
 * cutover) — unique (tipo, ano, mes) no schema.
 */
class SalvarIndiceEconomico
{
    public function executar(
        TipoIndiceEconomico $tipo,
        int $ano,
        int $mes,
        string $indice,
        ?IndiceEconomico $indiceEconomico = null,
    ): IndiceEconomico {
        $dados = ['tipo' => $tipo, 'ano' => $ano, 'mes' => $mes, 'indice' => $indice];

        if ($indiceEconomico === null) {
            return IndiceEconomico::query()->create($dados);
        }

        $indiceEconomico->update($dados);

        return $indiceEconomico;
    }
}
