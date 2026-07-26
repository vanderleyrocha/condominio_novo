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
}
