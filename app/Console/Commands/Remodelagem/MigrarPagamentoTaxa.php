<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use Illuminate\Support\Facades\DB;

/**
 * Passo 7 — pagamento_mensalidades → pagamento_taxa (02-mapeamento-de-para.md §5).
 * Migração 1:1 com ids preservados nas duas pontas; sinal do valor_aplicado
 * preservado (negativo em estornos).
 */
class MigrarPagamentoTaxa extends ComandoRemodelagem
{
    protected $signature = 'migrar:pagamento-taxa {--truncar}';

    protected $description = 'Remodelagem: pivot pagamento↔mensalidade → pagamento↔taxa';

    protected function tabelasDestino(): array
    {
        return ['pagamento_taxa'];
    }

    protected function entidadesMapa(): array
    {
        return [];
    }

    protected function executar(): int
    {
        $total = 0;

        DB::table('pagamento_mensalidades')->orderBy('id')->chunk(self::CHUNK, function ($pivots) use (&$total): void {
            DB::table('pagamento_taxa')->insert(array_map(fn (object $pm): array => [
                'id' => $pm->id,
                'pagamento_id' => $pm->pagamento_id,
                'taxa_condominial_id' => $pm->mensalidade_id,
                'valor_aplicado' => $pm->valor, // sinal preservado
                'created_at' => $pm->created_at,
                'updated_at' => $pm->updated_at,
            ], $pivots->all()));

            $total += $pivots->count();
        });

        $this->log("Aplicações pagamento↔taxa migradas: {$total}.");

        return self::SUCCESS;
    }
}
