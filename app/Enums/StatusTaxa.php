<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status persistido de taxas_condominiais — cache de leitura derivado da soma
 * de pagamento_taxa (03-modelo-dados.md). "Vencida" não é status persistido:
 * é derivada de vencimento < hoje sobre uma taxa não paga (semântica do legado).
 */
enum StatusTaxa: string
{
    case Aberto = 'aberto';
    case PagoParcial = 'pago_parcial';
    case Pago = 'pago';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberto => 'Em aberto',
            self::PagoParcial => 'Pago parcial',
            self::Pago => 'Pago',
        };
    }

    /**
     * Cores da grade anual — mesma semântica visual do legado
     * (StatusMensalidade::classeGrade): pago+contabilizado, pago+não
     * contabilizado, vencida sem pagamento, em dia.
     */
    public function classeGrade(bool $contabilizado = true, bool $vencida = false): string
    {
        return match (true) {
            $this === self::Pago || $this === self::PagoParcial => $contabilizado ? 'bg-status-pago' : 'bg-status-pago-nao-contabilizado',
            $vencida => 'bg-status-vencida',
            default => '',
        };
    }
}
