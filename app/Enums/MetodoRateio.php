<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cobranças extras migradas recebem Manual — o legado não guarda a regra
 * de rateio (02-mapeamento-de-para.md §6).
 */
enum MetodoRateio: string
{
    case FracaoIdeal = 'fracao_ideal';
    case Igualitario = 'igualitario';
    case Manual = 'manual';

    public function rotulo(): string
    {
        return match ($this) {
            self::FracaoIdeal => 'Fração ideal',
            self::Igualitario => 'Igualitário',
            self::Manual => 'Manual',
        };
    }
}
