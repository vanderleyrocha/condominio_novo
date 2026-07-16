<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validação de CPF com dígito verificador (decisão Q-03).
 * Espera valor já normalizado para 11 dígitos ou com máscara.
 */
final class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = preg_replace('/\D/', '', (string) $value) ?? '';

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('O campo :attribute não é um CPF válido.');

            return;
        }

        foreach ([9, 10] as $tamanho) {
            $soma = 0;
            for ($i = 0; $i < $tamanho; $i++) {
                $soma += (int) $cpf[$i] * (($tamanho + 1) - $i);
            }
            $digito = (10 * $soma) % 11 % 10;
            if ((int) $cpf[$tamanho] !== $digito) {
                $fail('O campo :attribute não é um CPF válido.');

                return;
            }
        }
    }

    public static function normalizar(string $cpf): string
    {
        return preg_replace('/\D/', '', $cpf) ?? '';
    }
}
