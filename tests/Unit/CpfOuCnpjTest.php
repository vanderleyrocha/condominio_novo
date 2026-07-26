<?php

declare(strict_types=1);

use App\Rules\CpfOuCnpj;

// Unit puro (sem container): chama a rule diretamente com um closure de falha.

function documentoFalha(string $valor): bool
{
    $falhou = false;
    (new CpfOuCnpj)->validate('documento', $valor, function () use (&$falhou): void {
        $falhou = true;
    });

    return $falhou;
}

it('aceita CPF válido com ou sem máscara', function () {
    expect(documentoFalha('390.533.447-05'))->toBeFalse()
        ->and(documentoFalha('39053344705'))->toBeFalse();
});

it('rejeita CPF com dígito verificador inválido', function () {
    expect(documentoFalha('390.533.447-06'))->toBeTrue();
});

it('aceita CNPJ válido com ou sem máscara', function () {
    // CNPJ da Casa da Moeda (DV verdadeiro): 34.028.316/0001-03? — usamos um DV calculável:
    expect(documentoFalha('11.222.333/0001-81'))->toBeFalse()
        ->and(documentoFalha('11222333000181'))->toBeFalse();
});

it('rejeita CNPJ com dígito verificador inválido', function () {
    expect(documentoFalha('11.222.333/0001-80'))->toBeTrue();
});

it('rejeita CNPJ com dígitos repetidos', function () {
    expect(documentoFalha('11111111111111'))->toBeTrue();
});

it('rejeita comprimentos inválidos', function () {
    expect(documentoFalha('123456'))->toBeTrue()
        ->and(documentoFalha('123456789012'))->toBeTrue();
});

it('normaliza para somente dígitos', function () {
    expect(CpfOuCnpj::normalizar('11.222.333/0001-81'))->toBe('11222333000181');
});
