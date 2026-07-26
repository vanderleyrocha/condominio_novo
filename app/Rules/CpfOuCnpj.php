<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CPF (11 dígitos) ou CNPJ (14 dígitos) com dígito verificador,
 * para pessoas.cpf_cnpj do modelo novo (fisica/juridica). CPF delega
 * para a rule Cpf existente (Q-03).
 */
final class CpfOuCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $documento = self::normalizar((string) $value);

        if (strlen($documento) === 11) {
            (new Cpf)->validate($attribute, $documento, $fail);

            return;
        }

        if (strlen($documento) !== 14 || preg_match('/^(\d)\1{13}$/', $documento)) {
            $fail('O campo :attribute não é um CPF ou CNPJ válido.');

            return;
        }

        foreach ([12, 13] as $posicao) {
            $pesos = $posicao === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += (int) $documento[$i] * $peso;
            }

            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $documento[$posicao] !== $digito) {
                $fail('O campo :attribute não é um CPF ou CNPJ válido.');

                return;
            }
        }
    }

    public static function normalizar(string $documento): string
    {
        return preg_replace('/\D/', '', $documento) ?? '';
    }
}
