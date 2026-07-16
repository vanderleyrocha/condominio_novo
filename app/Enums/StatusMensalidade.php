<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusMensalidade: string
{
    case Paga = 'paga';
    case PagaParcial = 'paga_parcial';
    case Vencida = 'vencida';
    case EmAberto = 'em_aberto';

    /**
     * Mapa de cores da grade anual — semântica herdada do legado
     * (MensalidadeController::editYear): pago+contabilizado, pago+não contabilizado,
     * vencida sem pagamento, em dia. Tokens em tokens-derived.md.
     */
    public function classeGrade(bool $contabilizado = true): string
    {
        return match ($this) {
            self::Paga, self::PagaParcial => $contabilizado ? 'bg-status-pago' : 'bg-status-pago-nao-contabilizado',
            self::Vencida => 'bg-status-vencida',
            self::EmAberto => '',
        };
    }
}
