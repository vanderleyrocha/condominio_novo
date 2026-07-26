<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusTaxa;
use App\Models\TaxaCondominial;

/**
 * Serviço ÚNICO de recálculo do status de taxas condominiais
 * (02-mapeamento-de-para.md §3): usado tanto pelo ETL da remodelagem
 * quanto pela aplicação em produção. Toda aritmética em BCMath sobre
 * strings decimais — nunca float (paridade golden files).
 *
 * valor_devido = valor_original + valor_acrescimo - valor_desconto
 * valor_pago_total = SUM(pagamento_taxa.valor_aplicado)
 *   (estornos entram negativos e se anulam naturalmente)
 */
class StatusTaxaService
{
    private const ESCALA = 2;

    /**
     * Cálculo puro, sem tocar no banco — testável isoladamente.
     */
    public function calcular(
        string $valorOriginal,
        string $valorDesconto,
        string $valorAcrescimo,
        string $valorPagoTotal,
    ): StatusTaxa {
        $valorDevido = bcsub(
            bcadd($valorOriginal, $valorAcrescimo, self::ESCALA),
            $valorDesconto,
            self::ESCALA
        );

        if (bccomp($valorPagoTotal, '0', self::ESCALA) <= 0) {
            return StatusTaxa::Aberto;
        }

        if (bccomp($valorPagoTotal, $valorDevido, self::ESCALA) >= 0) {
            return StatusTaxa::Pago;
        }

        return StatusTaxa::PagoParcial;
    }

    /**
     * Soma os pagamentos aplicados à taxa e persiste o status recalculado.
     * SUM do MySQL sobre decimal(10,2) retorna decimal exato (string no PDO).
     */
    public function recalcular(TaxaCondominial $taxa): StatusTaxa
    {
        $valorPagoTotal = (string) ($taxa->pagamentoTaxas()->sum('valor_aplicado') ?: '0');

        $status = $this->calcular(
            (string) $taxa->valor_original,
            (string) $taxa->valor_desconto,
            (string) $taxa->valor_acrescimo,
            $valorPagoTotal,
        );

        if ($taxa->status !== $status) {
            $taxa->forceFill(['status' => $status])->save();
        }

        return $status;
    }
}
