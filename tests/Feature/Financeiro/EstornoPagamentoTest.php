<?php

declare(strict_types=1);

// PAR-03 — parity_tests/03-estorno-pagamento.feature

use App\Actions\Financeiro\EstornarPagamento;
use App\Actions\Financeiro\RegistrarPagamento;
use App\Models\Imovel;
use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Models\Proprietario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cenarioPagamentoQuitado(string $valor = '150.00'): array
{
    $proprietario = Proprietario::query()->create([
        'nome' => 'Maria', 'cpf' => '39053344705', 'telefone' => '0', 'responsavel_pagamento' => 'proprietario',
    ]);
    $imovel = Imovel::query()->create(['proprietario_id' => $proprietario->id, 'nome' => 'Apto 101']);
    $mensalidade = Mensalidade::query()->create([
        'imovel_id' => $imovel->id, 'mes' => 1, 'ano' => 2025, 'vencimento' => '2025-01-31',
        'valor' => $valor, 'valor_pago' => '0.00', 'contabilizado' => true,
    ]);
    $pagamento = app(RegistrarPagamento::class)->executar(
        $proprietario, '2025-02-01', 'Pagamento', (float) $valor, [$mensalidade->id],
    );

    return [$pagamento, $mensalidade];
}

test('estorno total reabre a mensalidade (RN-16/RN-18)', function () {
    [$pagamento, $mensalidade] = cenarioPagamentoQuitado();

    $estorno = app(EstornarPagamento::class)->executar($pagamento, [$mensalidade->id => 150.00]);

    expect($estorno->pagamento_origem_id)->toBe($pagamento->id)
        ->and((float) $estorno->mensalidades()->first()->pivot->valor)->toBe(-150.00)
        ->and((float) $mensalidade->refresh()->valor_pago)->toBe(0.00)
        ->and($mensalidade->pago_em)->toBeNull()
        ->and($pagamento->refresh()->estornado)->toBeTrue();
});

test('pagamento estornado não pode ser estornado novamente (INV-04)', function () {
    [$pagamento, $mensalidade] = cenarioPagamentoQuitado();
    app(EstornarPagamento::class)->executar($pagamento, [$mensalidade->id => 150.00]);

    expect(fn () => app(EstornarPagamento::class)->executar($pagamento->refresh(), [$mensalidade->id => 1.00]))
        ->toThrow(DomainException::class, 'Este pagamento já foi estornado.');
});

test('estorno não supera o valor pago pelo pagamento original (RN-17)', function () {
    [$pagamento, $mensalidade] = cenarioPagamentoQuitado();

    expect(fn () => app(EstornarPagamento::class)->executar($pagamento, [$mensalidade->id => 200.00]))
        ->toThrow(DomainException::class);
});

test('estorno não supera o acumulado atual da mensalidade (EX-02 — correção P10)', function () {
    // Mensalidade quitada por dois pagamentos parciais de 75,00
    $proprietario = Proprietario::query()->create([
        'nome' => 'Maria', 'cpf' => '39053344705', 'telefone' => '0', 'responsavel_pagamento' => 'proprietario',
    ]);
    $imovel = Imovel::query()->create(['proprietario_id' => $proprietario->id, 'nome' => 'Apto 101']);
    $mensalidade = Mensalidade::query()->create([
        'imovel_id' => $imovel->id, 'mes' => 1, 'ano' => 2025, 'vencimento' => '2025-01-31',
        'valor' => '150.00', 'valor_pago' => '0.00', 'contabilizado' => true,
    ]);

    $pagamento1 = app(RegistrarPagamento::class)->executar($proprietario, '2025-02-01', 'Parcial 1', 75.00, [$mensalidade->id]);
    $pagamento2 = app(RegistrarPagamento::class)->executar($proprietario, '2025-02-10', 'Parcial 2', 75.00, [$mensalidade->id]);

    // Estorna integralmente o primeiro (mensalidade fica com 75 acumulado)
    app(EstornarPagamento::class)->executar($pagamento1, [$mensalidade->id => 75.00]);
    expect((float) $mensalidade->refresh()->valor_pago)->toBe(75.00);

    // No legado, estornar 75 do pagamento 2 passaria mesmo se o acumulado fosse menor;
    // aqui o teto 2 impede estorno acima do acumulado atual
    app(EstornarPagamento::class)->executar($pagamento2, [$mensalidade->id => 75.00]);
    expect((float) $mensalidade->refresh()->valor_pago)->toBe(0.00)
        ->and($mensalidade->pago_em)->toBeNull();
});

test('registro de estorno não pode ser estornado', function () {
    [$pagamento, $mensalidade] = cenarioPagamentoQuitado();
    $estorno = app(EstornarPagamento::class)->executar($pagamento, [$mensalidade->id => 150.00]);

    expect(fn () => app(EstornarPagamento::class)->executar($estorno, [$mensalidade->id => 1.00]))
        ->toThrow(DomainException::class, 'Não é possível estornar um registro de estorno.');
});

test('estorno parcial mantém a mensalidade parcialmente paga', function () {
    [$pagamento, $mensalidade] = cenarioPagamentoQuitado();

    app(EstornarPagamento::class)->executar($pagamento, [$mensalidade->id => 50.00]);

    expect((float) $mensalidade->refresh()->valor_pago)->toBe(100.00)
        ->and($mensalidade->pago_em)->not->toBeNull();
});
