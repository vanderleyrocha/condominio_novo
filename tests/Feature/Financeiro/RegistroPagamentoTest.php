<?php

declare(strict_types=1);

// PAR-02 — parity_tests/02-registro-pagamento.feature

use App\Actions\Financeiro\RegistrarPagamento;
use App\Models\Imovel;
use App\Models\Mensalidade;
use App\Models\Proprietario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cenarioProprietarioComImovel(): array
{
    $proprietario = Proprietario::query()->create([
        'nome' => 'Maria Silva',
        'cpf' => '39053344705',
        'telefone' => '68999990000',
        'responsavel_pagamento' => 'proprietario',
    ]);
    $imovel = Imovel::query()->create(['proprietario_id' => $proprietario->id, 'nome' => 'Apto 101']);

    return [$proprietario, $imovel];
}

function mensalidadeAberta(Imovel $imovel, int $ano, int $mes, string $valor, string $desconto = '0.00', string $acrescimo = '0.00'): Mensalidade
{
    return Mensalidade::query()->create([
        'imovel_id' => $imovel->id,
        'mes' => $mes,
        'ano' => $ano,
        'vencimento' => sprintf('%d-%02d-28', $ano, $mes),
        'valor' => $valor,
        'desconto' => $desconto,
        'acrescimo' => $acrescimo,
        'valor_pago' => '0.00',
        'contabilizado' => true,
    ]);
}

test('pagamento quita mensalidades em ordem cronológica (RN-11/RN-12)', function () {
    [$proprietario, $imovel] = cenarioProprietarioComImovel();
    $m1 = mensalidadeAberta($imovel, 2025, 1, '100.00');
    $m2 = mensalidadeAberta($imovel, 2025, 2, '100.00');
    $m3 = mensalidadeAberta($imovel, 2025, 3, '100.00');

    app(RegistrarPagamento::class)->executar(
        $proprietario, '2025-04-01', 'Pagamento teste', 250.00, [$m3->id, $m1->id, $m2->id],
    );

    expect((float) $m1->refresh()->valor_pago)->toBe(100.00)
        ->and($m1->pago_em)->not->toBeNull()
        ->and((float) $m2->refresh()->valor_pago)->toBe(100.00)
        ->and($m2->pago_em)->not->toBeNull()
        ->and((float) $m3->refresh()->valor_pago)->toBe(50.00);
});

test('devido considera acréscimo e desconto (RN-13)', function () {
    [$proprietario, $imovel] = cenarioProprietarioComImovel();
    $m = mensalidadeAberta($imovel, 2025, 1, '150.00', '20.00', '10.00');

    app(RegistrarPagamento::class)->executar($proprietario, '2025-02-01', 'Quitação', 140.00, [$m->id]);

    // devido = 150 + 10 - 20 - 0 = 140 → quitada integralmente
    expect((float) $m->refresh()->valor_pago)->toBe(140.00);
});

test('proprietário sem imóvel não registra pagamento (RN-15)', function () {
    $proprietario = Proprietario::query()->create([
        'nome' => 'Sem Imóvel',
        'cpf' => '52998224725',
        'telefone' => '68999990001',
        'responsavel_pagamento' => 'proprietario',
    ]);

    expect(fn () => app(RegistrarPagamento::class)->executar($proprietario, '2025-01-01', 'x', 100.00, [1]))
        ->toThrow(DomainException::class, 'Proprietário sem imóvel vinculado.');
});

test('mensalidade de outro imóvel não recebe distribuição', function () {
    [$proprietario, $imovel] = cenarioProprietarioComImovel();
    $outroProprietario = Proprietario::query()->create([
        'nome' => 'Outro', 'cpf' => '52998224725', 'telefone' => '0', 'responsavel_pagamento' => 'proprietario',
    ]);
    $outroImovel = Imovel::query()->create(['proprietario_id' => $outroProprietario->id, 'nome' => 'Apto 999']);
    $alheia = mensalidadeAberta($outroImovel, 2025, 1, '100.00');

    app(RegistrarPagamento::class)->executar($proprietario, '2025-02-01', 'x', 100.00, [$alheia->id]);

    expect((float) $alheia->refresh()->valor_pago)->toBe(0.00);
});

test('excedente não é redistribuído (RN-12)', function () {
    [$proprietario, $imovel] = cenarioProprietarioComImovel();
    $m = mensalidadeAberta($imovel, 2025, 1, '100.00');

    $pagamento = app(RegistrarPagamento::class)->executar($proprietario, '2025-02-01', 'x', 500.00, [$m->id]);

    expect((float) $m->refresh()->valor_pago)->toBe(100.00)
        ->and((float) $pagamento->mensalidades()->first()->pivot->valor)->toBe(100.00);
});
