<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\MetodoRateio;
use App\Support\Remodelagem\MapaIds;
use Illuminate\Support\Facades\DB;

/**
 * Passo 9 — cobrancas_extras → cobrancas_extraordinarias + pivot
 * cobranca_extra_mensalidade → cobranca_extraordinaria_taxa
 * (02-mapeamento-de-para.md §6 e §7). Ids preservados;
 * metodo_rateio = 'manual' (o legado não guarda a regra).
 */
class MigrarCobrancasExtraordinarias extends ComandoRemodelagem
{
    protected $signature = 'migrar:cobrancas-extraordinarias {--truncar}';

    protected $description = 'Remodelagem: cobranças extras → cobranças extraordinárias (+ pivot)';

    protected function tabelasDestino(): array
    {
        return ['cobranca_extraordinaria_taxa', 'cobrancas_extraordinarias'];
    }

    protected function entidadesMapa(): array
    {
        return ['cobranca_extraordinaria'];
    }

    protected function executar(): int
    {
        $condominioId = $this->condominioId();
        $total = 0;
        $totalPivot = 0;

        DB::table('cobrancas_extras')->orderBy('id')->chunk(self::CHUNK, function ($cobrancas) use ($condominioId, &$total): void {
            $linhas = [];
            $mapa = [];

            foreach ($cobrancas as $c) {
                $linhas[] = [
                    'id' => $c->id,
                    'condominio_id' => $condominioId,
                    'nome' => $c->nome,
                    'valor_total' => $c->valor,
                    'metodo_rateio' => MetodoRateio::Manual->value,
                    'vigencia_inicio' => $c->vigencia_inicio,
                    'vigencia_fim' => $c->vigencia_fim,
                    'ativa' => $c->ativa,
                    'created_at' => $c->created_at,
                    'updated_at' => $c->updated_at,
                ];
                $mapa[] = ['id_antigo' => (int) $c->id, 'id_novo' => (int) $c->id];
            }

            DB::table('cobrancas_extraordinarias')->insert($linhas);
            MapaIds::registrarLote('cobranca_extraordinaria', $mapa);
            $total += count($linhas);
        });

        DB::table('cobranca_extra_mensalidade')->orderBy('id')->chunk(self::CHUNK, function ($pivots) use (&$totalPivot): void {
            DB::table('cobranca_extraordinaria_taxa')->insert(array_map(fn (object $p): array => [
                'id' => $p->id,
                'cobranca_extraordinaria_id' => $p->cobranca_extra_id,
                'taxa_condominial_id' => $p->mensalidade_id,
                'valor' => $p->valor,
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
            ], $pivots->all()));

            $totalPivot += $pivots->count();
        });

        $this->log("Cobranças extraordinárias migradas: {$total} (metodo_rateio='manual'); vínculos com taxas: {$totalPivot}.");

        return self::SUCCESS;
    }
}
