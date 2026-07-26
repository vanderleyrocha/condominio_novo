<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoPessoa: string
{
    case Fisica = 'fisica';
    case Juridica = 'juridica';
}
