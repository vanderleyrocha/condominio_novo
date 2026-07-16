<?php

declare(strict_types=1);

// PAR-07 — parity_tests/07-permissoes.feature (matriz permissions.md)

use App\Actions\Financeiro\AtualizarGradeAnual;
use App\Actions\Financeiro\AtualizarMensalidade;
use App\Enums\PapelUsuario;
use App\Models\Imovel;
use App\Models\Mensalidade;
use App\Models\Parametro;
use App\Models\Proprietario;
use App\Models\User;
use App\Support\ParametrosCondominio;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    Parametro::query()->create(['chave' => 'data_corte_level_one', 'valor' => '2024-03-10']);
    ParametrosCondominio::limparCache();
});

function usuario(PapelUsuario $papel): User
{
    static $n = 0;
    $n++;

    return User::query()->create([
        'name' => "user{$n}-".$papel->value,
        'email' => "user{$n}@teste.local",
        'password' => 'password123',
        'papel' => $papel,
    ]);
}

function mensalidadePagaEm(?string $pagoEm): Mensalidade
{
    $proprietario = Proprietario::query()->firstOrCreate(
        ['cpf' => '39053344705'],
        ['nome' => 'Dono', 'telefone' => '0', 'responsavel_pagamento' => 'proprietario'],
    );
    $imovel = Imovel::query()->firstOrCreate(
        ['nome' => 'Apto 101'],
        ['proprietario_id' => $proprietario->id],
    );

    return Mensalidade::query()->create([
        'imovel_id' => $imovel->id, 'mes' => 1, 'ano' => 2024, 'vencimento' => '2024-01-31',
        'valor' => '150.00', 'valor_pago' => $pagoEm !== null ? '150.00' : '0.00',
        'pago_em' => $pagoEm, 'contabilizado' => true,
    ]);
}

test('admin edita mensalidade de qualquer data', function () {
    $admin = usuario(PapelUsuario::Admin);
    $mensalidade = mensalidadePagaEm('2023-05-10');

    expect(Gate::forUser($admin)->allows('update', $mensalidade))->toBeTrue();
});

test('level-one não edita mensalidade paga antes da data de corte (RN-07)', function () {
    $levelOne = usuario(PapelUsuario::LevelOne);
    $mensalidade = mensalidadePagaEm('2024-03-01');

    expect(Gate::forUser($levelOne)->allows('update', $mensalidade))->toBeFalse();
});

test('level-one edita mensalidade com pago_em nulo ou posterior ao corte (RN-07)', function () {
    $levelOne = usuario(PapelUsuario::LevelOne);

    expect(Gate::forUser($levelOne)->allows('update', mensalidadePagaEm(null)))->toBeTrue()
        ->and(Gate::forUser($levelOne)->allows('update', mensalidadePagaEm('2024-03-11')))->toBeTrue();
});

test('level-one nunca grava contabilizado como falso (RN-08)', function () {
    $levelOne = usuario(PapelUsuario::LevelOne);
    $mensalidade = mensalidadePagaEm('2024-04-01');

    app(AtualizarMensalidade::class)->executar($mensalidade, [
        'valor' => '150.00', 'desconto' => '0.00', 'acrescimo' => '0.00',
        'valor_pago' => '150.00', 'pago_em' => '2024-04-01', 'vencimento' => '2024-01-31',
        'contabilizado' => false,
    ], $levelOne);

    expect($mensalidade->refresh()->contabilizado)->toBeTrue();
});

test('admin pode gravar contabilizado como falso (RN-08)', function () {
    $admin = usuario(PapelUsuario::Admin);
    $mensalidade = mensalidadePagaEm('2024-04-01');

    app(AtualizarMensalidade::class)->executar($mensalidade, [
        'valor' => '150.00', 'desconto' => '0.00', 'acrescimo' => '0.00',
        'valor_pago' => '150.00', 'pago_em' => '2024-04-01', 'vencimento' => '2024-01-31',
        'contabilizado' => false,
    ], $admin);

    expect($mensalidade->refresh()->contabilizado)->toBeFalse();
});

test('valor_pago zero limpa pago_em; valor_pago sem data usa hoje (RN-09/RN-10)', function () {
    $admin = usuario(PapelUsuario::Admin);

    $mensalidade = mensalidadePagaEm('2024-04-01');
    app(AtualizarMensalidade::class)->executar($mensalidade, [
        'valor' => '150.00', 'desconto' => '0.00', 'acrescimo' => '0.00',
        'valor_pago' => '0.00', 'pago_em' => '2024-04-01', 'vencimento' => '2024-01-31',
    ], $admin);
    expect($mensalidade->refresh()->pago_em)->toBeNull();

    $outra = mensalidadePagaEm(null);
    app(AtualizarMensalidade::class)->executar($outra, [
        'valor' => '150.00', 'desconto' => '0.00', 'acrescimo' => '0.00',
        'valor_pago' => '150.00', 'pago_em' => null, 'vencimento' => '2024-01-31',
    ], $admin);
    expect($outra->refresh()->pago_em?->toDateString())->toBe(now()->toDateString());
});

test('edição em massa exige a mesma Policy da edição individual (EX-05)', function () {
    $levelOne = usuario(PapelUsuario::LevelOne);
    $bloqueada = mensalidadePagaEm('2024-03-01'); // antes do corte

    expect(fn () => app(AtualizarGradeAnual::class)->executar([$bloqueada->id => '10.00'], $levelOne))
        ->toThrow(AuthorizationException::class);

    expect((float) $bloqueada->refresh()->valor_pago)->toBe(150.00);
});

test('grade anual grava apenas células alteradas (persistência seletiva)', function () {
    $admin = usuario(PapelUsuario::Admin);
    $inalterada = mensalidadePagaEm('2024-04-01'); // valor_pago 150.00
    $alterada = mensalidadePagaEm(null);           // valor_pago 0.00

    $gravadas = app(AtualizarGradeAnual::class)->executar([
        $inalterada->id => '150.00',
        $alterada->id => '150.00',
    ], $admin);

    expect($gravadas)->toBe(1)
        ->and((float) $alterada->refresh()->valor_pago)->toBe(150.00)
        ->and($alterada->pago_em)->not->toBeNull();
});
