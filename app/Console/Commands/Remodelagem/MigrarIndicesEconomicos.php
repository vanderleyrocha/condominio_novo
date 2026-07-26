<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\TipoIndiceEconomico;
use Illuminate\Support\Facades\DB;

/**
 * Passo 11 — ipcas → indices_economicos com tipo='ipca'
 * (02-mapeamento-de-para.md §9).
 */
class MigrarIndicesEconomicos extends ComandoRemodelagem
{
    protected $signature = 'migrar:indices-economicos {--truncar}';

    protected $description = 'Remodelagem: ipcas → índices econômicos';

    protected function tabelasDestino(): array
    {
        return ['indices_economicos'];
    }

    protected function entidadesMapa(): array
    {
        return [];
    }

    protected function executar(): int
    {
        $total = 0;

        DB::table('ipcas')->orderBy('id')->chunk(self::CHUNK, function ($ipcas) use (&$total): void {
            DB::table('indices_economicos')->insert(array_map(fn (object $i): array => [
                'tipo' => TipoIndiceEconomico::Ipca->value,
                'ano' => $i->ano,
                'mes' => $i->mes,
                'indice' => $i->indice,
                'created_at' => $i->created_at,
                'updated_at' => $i->updated_at,
            ], $ipcas->all()));

            $total += $ipcas->count();
        });

        $this->log("Índices IPCA migrados: {$total}.");

        return self::SUCCESS;
    }
}
