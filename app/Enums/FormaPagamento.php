<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * O legado não registra forma de pagamento — registros migrados recebem
 * NaoInformado (02-mapeamento-de-para.md §4).
 */
enum FormaPagamento: string
{
    case Dinheiro = 'dinheiro';
    case Pix = 'pix';
    case Transferencia = 'transferencia';
    case Boleto = 'boleto';
    case Cartao = 'cartao';
    case Cheque = 'cheque';
    case Outro = 'outro';
    case NaoInformado = 'nao_informado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Dinheiro => 'Dinheiro',
            self::Pix => 'PIX',
            self::Transferencia => 'Transferência',
            self::Boleto => 'Boleto',
            self::Cartao => 'Cartão',
            self::Cheque => 'Cheque',
            self::Outro => 'Outro',
            self::NaoInformado => 'Não informado',
        };
    }
}
