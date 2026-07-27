<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Enums\MetodoRateio;
use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use RuntimeException;

/**
 * Cobranças extraordinárias no modelo novo (substitui SalvarCobrancaExtra):
 * ganha metodo_rateio e escopo por condomínio.
 *
 * Etapa 4 de 05-plano-composicao-taxas.md: a cobrança é a CAMPANHA (D-04) —
 * `finalidade_id` diz para que serve a arrecadação e `valor_por_unidade` é o
 * valor do item gerado em cada competência dentro da vigência (LancarTaxas e
 * AplicarCobrancaEmTaxas). Sem valor_por_unidade ela é só um alvo de
 * arrecadação com rateio manual.
 */
class SalvarCobrancaExtraordinaria
{
    /**
     * @param  array{nome: string, valor_total: string, metodo_rateio: MetodoRateio|string, vigencia_inicio: string, vigencia_fim?: ?string, ativa?: bool, finalidade_id?: ?int, valor_por_unidade?: ?string}  $dados
     */
    public function executar(array $dados, ?CobrancaExtraordinaria $cobranca = null): CobrancaExtraordinaria
    {
        if ($cobranca === null) {
            $condominioId = Condominio::query()->value('id')
                ?? throw new RuntimeException('Nenhum condomínio cadastrado — rode migrar:condominios.');

            return CobrancaExtraordinaria::query()->create($dados + ['condominio_id' => $condominioId]);
        }

        $cobranca->update($dados);

        return $cobranca;
    }
}
