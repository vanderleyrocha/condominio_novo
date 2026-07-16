<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Conversão de moeda no formato brasileiro (BR-MIGRAR-020).
 * Toda aritmética monetária do sistema usa strings decimais/BCMath — nunca float (DA-07).
 */
final class DinheiroBr
{
    /**
     * "1.500,00" -> "1500.00" | "1500.00" -> "1500.00" | "" -> "0.00"
     */
    public static function paraDecimal(?string $valorBr): string
    {
        $valorBr = trim((string) $valorBr);

        if ($valorBr === '') {
            return '0.00';
        }

        // Formato BR: pontos são separadores de milhar, vírgula é decimal
        if (str_contains($valorBr, ',')) {
            $valorBr = str_replace('.', '', $valorBr);
            $valorBr = str_replace(',', '.', $valorBr);
        }

        if (! is_numeric($valorBr)) {
            throw new \InvalidArgumentException("Valor monetário inválido: {$valorBr}");
        }

        return number_format((float) $valorBr, 2, '.', '');
    }

    /**
     * "1500.00" -> "1.500,00"
     */
    public static function formatar(string|float|int $decimal): string
    {
        return number_format((float) $decimal, 2, ',', '.');
    }
}
