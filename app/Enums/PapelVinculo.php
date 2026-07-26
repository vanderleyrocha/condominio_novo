<?php

declare(strict_types=1);

namespace App\Enums;

enum PapelVinculo: string
{
    case Proprietario = 'proprietario';
    case Inquilino = 'inquilino';
    case Procurador = 'procurador';
}
