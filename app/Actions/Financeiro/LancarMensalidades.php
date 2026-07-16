<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Imovel;
use App\Models\Mensalidade;
use App\Support\ParametrosCondominio;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Lançamento em lote (BR-MIGRAR-001): 12 mensalidades × todos os imóveis do ano,
 * vencimento no último dia de cada mês, contabilizado = 1.
 *
 * Divergências deliberadas do legado (exceções de paridade):
 *  - EX-01: relançar o mesmo ano é bloqueado (o legado duplicava — RN-04);
 *  - transação atômica (o legado permitia lote parcial em falha).
 */
class LancarMensalidades
{
    public function executar(int $ano, ?string $valor = null): int
    {
        $valor ??= ParametrosCondominio::taxaMensalidadePadrao();

        return DB::transaction(function () use ($ano, $valor): int {
            if (Mensalidade::query()->where('ano', $ano)->exists()) {
                throw new DomainException("As mensalidades do ano {$ano} já foram lançadas.");
            }

            $imoveis = Imovel::query()->orderBy('id')->get();

            if ($imoveis->isEmpty()) {
                throw new DomainException('Não há imóveis cadastrados para lançar mensalidades.');
            }

            $agora = now();
            $linhas = [];

            foreach ($imoveis as $imovel) {
                for ($mes = 1; $mes <= 12; $mes++) {
                    // Vencimento no último dia do mês — paridade com date("Y-m-t") (RN-03)
                    $vencimento = sprintf('%d-%02d-%02d', $ano, $mes, cal_days_in_month(CAL_GREGORIAN, $mes, $ano));

                    $linhas[] = [
                        'imovel_id' => $imovel->id,
                        'mes' => $mes,
                        'ano' => $ano,
                        'vencimento' => $vencimento,
                        'valor' => $valor,
                        'desconto' => '0.00',
                        'acrescimo' => '0.00',
                        'valor_pago' => '0.00',
                        'pago_em' => null,
                        'contabilizado' => true,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                }
            }

            Mensalidade::query()->insert($linhas);

            return count($linhas);
        });
    }
}
