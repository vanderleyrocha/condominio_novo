<?php

declare(strict_types=1);

use App\Services\RateioPorFinalidadeService;

// Teste Unit puro (sem DB): exercita exclusivamente a cascata BCMath do rateio
// por finalidade (D-03 de docs/migration/05-plano-composicao-taxas.md).

$service = fn (): RateioPorFinalidadeService => new RateioPorFinalidadeService;

// Composição típica pós-decomposição: 100 de custeio (ordem 0) + 50 de pintura (ordem 1)
$composicao = [
    ['id' => 1, 'valor' => '100.00'],
    ['id' => 2, 'valor' => '50.00'],
];

it('não atribui nada quando nada foi pago', function () use ($service, $composicao) {
    expect($service()->distribuir('0.00', $composicao))
        ->toBe([1 => '0.00', 2 => '0.00']);
});

it('quita o item ordinário antes da contribuição em pagamento parcial', function () use ($service, $composicao) {
    // 75 pagos numa taxa 100+50 → 75 para o custeio, 0 para a pintura (D-03)
    expect($service()->distribuir('75.00', $composicao))
        ->toBe([1 => '75.00', 2 => '0.00']);
});

it('transborda para a contribuição só depois de quitar o ordinário', function () use ($service, $composicao) {
    expect($service()->distribuir('120.00', $composicao))
        ->toBe([1 => '100.00', 2 => '20.00']);
});

it('atribui integralmente quando a taxa está quitada', function () use ($service, $composicao) {
    expect($service()->distribuir('150.00', $composicao))
        ->toBe([1 => '100.00', 2 => '50.00']);
});

it('não redistribui excedente além da soma dos itens', function () use ($service, $composicao) {
    // 165 pagos (taxa com acréscimo de 15, que vive no nível da taxa)
    expect($service()->distribuir('165.00', $composicao))
        ->toBe([1 => '100.00', 2 => '50.00'])
        ->and($service()->excedente('165.00', $composicao))->toBe('15.00');
});

it('não gera atribuição quando o estorno supera o pago', function () use ($service, $composicao) {
    // pagamento de 150 + estorno de -160 → soma negativa (convenção de sinal)
    expect($service()->distribuir('-10.00', $composicao))
        ->toBe([1 => '0.00', 2 => '0.00'])
        ->and($service()->excedente('-10.00', $composicao))->toBe('0.00');
});

it('anula a atribuição quando o estorno zera o pagamento', function () use ($service, $composicao) {
    expect($service()->distribuir('0.00', $composicao))
        ->toBe([1 => '0.00', 2 => '0.00']);
});

it('lida com centavos sem erro de ponto flutuante', function () use ($service) {
    $itens = [
        ['id' => 1, 'valor' => '33.33'],
        ['id' => 2, 'valor' => '33.33'],
        ['id' => 3, 'valor' => '33.34'],
    ];

    expect($service()->distribuir('100.00', $itens))
        ->toBe([1 => '33.33', 2 => '33.33', 3 => '33.34'])
        ->and($service()->distribuir('66.66', $itens))
        ->toBe([1 => '33.33', 2 => '33.33', 3 => '0.00'])
        ->and($service()->excedente('100.00', $itens))->toBe('0.00');
});

it('ignora item de valor zero sem consumir saldo', function () use ($service) {
    $itens = [
        ['id' => 1, 'valor' => '0.00'],
        ['id' => 2, 'valor' => '50.00'],
    ];

    expect($service()->distribuir('50.00', $itens))
        ->toBe([1 => '0.00', 2 => '50.00']);
});
