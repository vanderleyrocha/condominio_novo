<?php

declare(strict_types=1);

// PAR-04 — parity_tests/04-correcao-monetaria.feature

use App\Enums\MetodoCorrecao;
use App\Models\Ipca;
use App\Services\CorrecaoMonetariaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('correção por soma simples com extremidades inclusivas (RN-21/RN-22)', function () {
    Ipca::query()->create(['ano' => 2025, 'mes' => 1, 'indice' => '0.50']);
    Ipca::query()->create(['ano' => 2025, 'mes' => 2, 'indice' => '0.40']);
    Ipca::query()->create(['ano' => 2025, 'mes' => 3, 'indice' => '0.10']);

    $servico = app(CorrecaoMonetariaService::class);
    $corrigido = $servico->corrigirMensalidade(
        100.00, Carbon::parse('2025-01-31'), Carbon::parse('2025-03-15'), MetodoCorrecao::SomaSimples,
    );

    // 100 * (1 + (0.50+0.40+0.10)/100) = 101.00 — ambas as extremidades inclusas
    expect($corrigido)->toBe(101.00);

    $memoria = $servico->memoriaCalculo(
        100.00, Carbon::parse('2025-01-31'), Carbon::parse('2025-03-15'), MetodoCorrecao::SomaSimples,
    );
    expect($memoria['indices'])->toHaveCount(3)
        ->and($memoria['ipca_acumulado'])->toBe(1.0)
        ->and($memoria['valor_corrigido'])->toBe(101.00);
});

test('sem correção quando vencimento não é anterior à data-base (guarda)', function () {
    Ipca::query()->create(['ano' => 2025, 'mes' => 6, 'indice' => '1.00']);

    $corrigido = app(CorrecaoMonetariaService::class)->corrigirMensalidade(
        100.00, Carbon::parse('2025-06-30'), Carbon::parse('2025-06-30'),
    );

    expect($corrigido)->toBe(100.00);
});

test('arredondamento de centavos idêntico ao legado', function () {
    Ipca::query()->create(['ano' => 2025, 'mes' => 1, 'indice' => '0.33']);
    Ipca::query()->create(['ano' => 2025, 'mes' => 2, 'indice' => '0.00']);

    $corrigido = app(CorrecaoMonetariaService::class)->corrigirMensalidade(
        149.99, Carbon::parse('2025-01-31'), Carbon::parse('2025-02-10'), MetodoCorrecao::SomaSimples,
    );

    // round(149.99 * 1.0033, 2) = round(150.4849..., 2) = 150.48
    expect($corrigido)->toBe(150.48);
});

test('método composto aplica índices mês a mês (EX-10 — novo, fora do contrato de paridade)', function () {
    Ipca::query()->create(['ano' => 2025, 'mes' => 1, 'indice' => '1.00']);
    Ipca::query()->create(['ano' => 2025, 'mes' => 2, 'indice' => '1.00']);

    $servico = app(CorrecaoMonetariaService::class);

    $simples = $servico->corrigirMensalidade(100.00, Carbon::parse('2025-01-15'), Carbon::parse('2025-02-15'), MetodoCorrecao::SomaSimples);
    $composta = $servico->corrigirMensalidade(100.00, Carbon::parse('2025-01-15'), Carbon::parse('2025-02-15'), MetodoCorrecao::Composta);

    expect($simples)->toBe(102.00)      // 100 * (1 + 2/100)
        ->and($composta)->toBe(102.01); // 100 * 1.01 * 1.01
});
