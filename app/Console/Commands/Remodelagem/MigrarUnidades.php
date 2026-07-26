<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Support\Remodelagem\MapaIds;
use Illuminate\Support\Facades\DB;

/**
 * Passo 3 — imoveis → unidades (02-mapeamento-de-para.md §2).
 * Ids preservados (mapa identidade), bloco_id/fracao_ideal nulos
 * (sem dado de origem). O vínculo com o proprietário é criado por
 * migrar:vinculos.
 */
class MigrarUnidades extends ComandoRemodelagem
{
    protected $signature = 'migrar:unidades {--truncar}';

    protected $description = 'Remodelagem: imóveis → unidades';

    protected function tabelasDestino(): array
    {
        return ['unidades'];
    }

    protected function entidadesMapa(): array
    {
        return ['unidade'];
    }

    protected function executar(): int
    {
        $condominioId = $this->condominioId();
        $total = 0;

        DB::table('imoveis')->orderBy('id')->chunk(self::CHUNK, function ($imoveis) use ($condominioId, &$total): void {
            $linhas = [];
            $mapa = [];

            foreach ($imoveis as $imovel) {
                $linhas[] = [
                    'id' => $imovel->id,
                    'condominio_id' => $condominioId,
                    'bloco_id' => null,
                    'identificacao' => $imovel->nome,
                    'fracao_ideal' => null,
                    'area' => null,
                    'vagas_garagem' => 0,
                    'created_at' => $imovel->created_at,
                    'updated_at' => $imovel->updated_at,
                ];
                $mapa[] = ['id_antigo' => (int) $imovel->id, 'id_novo' => (int) $imovel->id];
            }

            DB::table('unidades')->insert($linhas);
            MapaIds::registrarLote('unidade', $mapa);
            $total += count($linhas);
        });

        $this->log("Unidades migradas: {$total} (ids preservados).");

        return self::SUCCESS;
    }
}
