<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\FormaPagamento;
use App\Support\Remodelagem\MapaIds;
use Illuminate\Support\Facades\DB;

/**
 * Passo 6 — pagamentos (antigo) → pagamentos_novo (02-mapeamento-de-para.md §4).
 * Ids preservados; sinal NEGATIVO dos estornos mantido; estorno_de_id vem de
 * pagamento_origem_id (a linha de estorno é quem aponta para a original —
 * semântica de EstornarPagamento). Processa em ordem de id, garantindo que a
 * original exista antes do estorno (FK autorreferente).
 */
class MigrarPagamentos extends ComandoRemodelagem
{
    protected $signature = 'migrar:pagamentos {--truncar}';

    protected $description = 'Remodelagem: pagamentos antigos → pagamentos_novo';

    protected function tabelasDestino(): array
    {
        return ['pagamentos_novo'];
    }

    protected function entidadesMapa(): array
    {
        return ['pagamento'];
    }

    protected function executar(): int
    {
        $mapaPessoa = MapaIds::carregar('pessoa');
        $total = 0;
        $estornos = 0;

        DB::table('pagamentos')->orderBy('id')->chunk(self::CHUNK, function ($pagamentos) use ($mapaPessoa, &$total, &$estornos): void {
            $linhas = [];
            $mapa = [];

            foreach ($pagamentos as $p) {
                $pessoaId = $mapaPessoa[$p->proprietario_id]
                    ?? throw new \RuntimeException(
                        "Pagamento {$p->id}: proprietario {$p->proprietario_id} sem pessoa mapeada — rode migrar:pessoas."
                    );

                if ($p->pagamento_origem_id !== null) {
                    $estornos++;
                }

                $linhas[] = [
                    'id' => $p->id,
                    'unidade_id' => $p->imovel_id, // nullable; ids de unidades preservados
                    'pessoa_id' => $pessoaId,
                    'data_pagamento' => $p->data,
                    'descricao' => $p->descricao,
                    'valor_total' => $p->valor, // sinal preservado (negativo em estornos)
                    'forma_pagamento' => FormaPagamento::NaoInformado->value, // inexistente no legado
                    'estorno_de_id' => $p->pagamento_origem_id,
                    'created_at' => $p->created_at,
                    'updated_at' => $p->updated_at,
                ];
                $mapa[] = ['id_antigo' => (int) $p->id, 'id_novo' => (int) $p->id];
            }

            DB::table('pagamentos_novo')->insert($linhas);
            MapaIds::registrarLote('pagamento', $mapa);
            $total += count($linhas);
        });

        $this->log("Pagamentos migrados: {$total} (estornos: {$estornos}, ids preservados).");
        $this->log("forma_pagamento = 'nao_informado' em todos — decisão de produto pendente (02-mapeamento §4).");

        return self::SUCCESS;
    }
}
