<?php

declare(strict_types=1);

namespace App\Enums;

enum NaturezaLancamento: string
{
    case Receita = 'receita';
    case Despesa = 'despesa';
}
