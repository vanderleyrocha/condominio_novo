<?php

declare(strict_types=1);

// PAR-09 — parity_tests/09-cadastros-guardas.feature

use App\Actions\Cadastros\ExcluirProprietario;
use App\Models\Imovel;
use App\Models\Pagamento;
use App\Models\Proprietario;
use App\Rules\Cpf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function novoProprietario(string $cpf = '39053344705'): Proprietario
{
    return Proprietario::query()->create([
        'nome' => 'Dono Teste', 'cpf' => $cpf, 'telefone' => '0', 'responsavel_pagamento' => 'proprietario',
    ]);
}

test('proprietário com imóveis não pode ser excluído (RN-28)', function () {
    $proprietario = novoProprietario();
    Imovel::query()->create(['proprietario_id' => $proprietario->id, 'nome' => 'Apto 101']);

    expect(fn () => app(ExcluirProprietario::class)->executar($proprietario))
        ->toThrow(DomainException::class, 'Não é possível excluir: proprietário possui imóveis vinculados.');

    expect(Proprietario::query()->count())->toBe(1);
});

test('proprietário com pagamentos e sem imóveis não pode ser excluído (guarda estendida Q-05)', function () {
    $proprietario = novoProprietario();
    Pagamento::query()->create([
        'proprietario_id' => $proprietario->id, 'data' => '2025-01-01',
        'descricao' => 'Pagamento avulso', 'valor' => '150.00',
    ]);

    expect(fn () => app(ExcluirProprietario::class)->executar($proprietario))
        ->toThrow(DomainException::class, 'Não é possível excluir: proprietário possui pagamentos registrados.');
});

test('proprietário sem vínculos pode ser excluído', function () {
    $proprietario = novoProprietario();

    app(ExcluirProprietario::class)->executar($proprietario);

    expect(Proprietario::query()->count())->toBe(0);
});

test('CPF com dígitos repetidos é rejeitado', function () {
    $validador = Validator::make(['cpf' => '111.111.111-11'], ['cpf' => [new Cpf]]);

    expect($validador->fails())->toBeTrue();
});

test('CPF com dígito verificador inválido é rejeitado', function () {
    $validador = Validator::make(['cpf' => '390.533.447-06'], ['cpf' => [new Cpf]]);

    expect($validador->fails())->toBeTrue();
});

test('CPF válido é aceito e normalizado para 11 dígitos', function () {
    $validador = Validator::make(['cpf' => '390.533.447-05'], ['cpf' => [new Cpf]]);

    expect($validador->passes())->toBeTrue()
        ->and(Cpf::normalizar('390.533.447-05'))->toBe('39053344705');
});

test('CPF duplicado é bloqueado pela unique do banco', function () {
    novoProprietario('39053344705');

    expect(fn () => novoProprietario('39053344705'))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});
