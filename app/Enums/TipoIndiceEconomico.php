<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoIndiceEconomico: string
{
    case Ipca = 'ipca';
    case Igpm = 'igpm';
    case Incc = 'incc';
}
