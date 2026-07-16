<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nomes de meses em português — paridade com Util::meses()/mesesAbr() do legado.
 */
final class MesesBr
{
    /** @return array<int, string> */
    public static function todos(): array
    {
        return [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
    }

    /** @return array<int, string> */
    public static function abreviados(): array
    {
        return [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];
    }

    public static function nome(int $mes): string
    {
        return self::todos()[$mes] ?? (string) $mes;
    }
}
