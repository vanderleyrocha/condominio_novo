<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoPessoa: string
{
    case Fisica = 'fisica';
    case Juridica = 'juridica';

    public function rotulo(): string
    {
        return match ($this) {
            self::Fisica => 'Pessoa Física',
            self::Juridica => 'Pessoa Jurídica',
        };
    }
}
