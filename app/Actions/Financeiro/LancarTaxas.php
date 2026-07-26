<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Enums\StatusTaxa;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Support\ConfiguracoesCondominio;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Lançamento em lote no modelo novo (substitui LancarMensalidades no cutover):
 * 12 taxas × todas as unidades do ano, vencimento no último dia de cada mês,
 * contabilizado = 1, status inicial 'aberto'. Relançar o mesmo ano é bloqueado
 * (EX-01) e a unique (unidade, ano, mes) garante no banco.
 */
class LancarTaxas
{
    public function executar(int $ano, ?string $valor = null): int
    {
        $valor ??= ConfiguracoesCondominio::taxaMensalidadePadrao();

        return DB::transaction(function () use ($ano, $valor): int {
            if (TaxaCondominial::query()->where('competencia_ano', $ano)->exists()) {
                throw new DomainException("As taxas do ano {$ano} já foram lançadas.");
            }

            $unidades = Unidade::query()->orderBy('id')->get();

            if ($unidades->isEmpty()) {
                throw new DomainException('Não há unidades cadastradas para lançar taxas.');
            }

            $agora = now();
            $linhas = [];

            foreach ($unidades as $unidade) {
                for ($mes = 1; $mes <= 12; $mes++) {
                    // Vencimento no último dia do mês — paridade com o legado (RN-03)
                    $vencimento = sprintf('%d-%02d-%02d', $ano, $mes, cal_days_in_month(CAL_GREGORIAN, $mes, $ano));

                    $linhas[] = [
                        'unidade_id' => $unidade->id,
                        'competencia_mes' => $mes,
                        'competencia_ano' => $ano,
                        'vencimento' => $vencimento,
                        'valor_original' => $valor,
                        'valor_desconto' => '0.00',
                        'valor_acrescimo' => '0.00',
                        'status' => StatusTaxa::Aberto->value,
                        'contabilizado' => true,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                }
            }

            TaxaCondominial::query()->insert($linhas);

            return count($linhas);
        });
    }
}
