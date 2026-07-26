<?php

declare(strict_types=1);

namespace App\Enums;

enum PapelVinculo: string
{
    case Proprietario = 'proprietario';
    case Inquilino = 'inquilino';
    case Procurador = 'procurador';

    public function rotulo(): string
    {
        return match ($this) {
            self::Proprietario => 'Proprietário',
            self::Inquilino => 'Inquilino',
            self::Procurador => 'Procurador',
        };
    }
}
