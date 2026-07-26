<?php

declare(strict_types=1);

use App\Enums\StatusTaxa;
use App\Services\StatusTaxaService;

// Teste Unit puro (sem DB — pdo_sqlite indisponível no CLI local):
// exercita exclusivamente a aritmética BCMath do cálculo de status.

$service = fn (): StatusTaxaService => new StatusTaxaService;

it('retorna aberto quando nada foi pago', function () use ($service) {
    expect($service()->calcular('350.00', '0.00', '0.00', '0.00'))
        ->toBe(StatusTaxa::Aberto);
});

it('retorna aberto quando a soma de pagamentos e estornos se anula', function () use ($service) {
    // pagamento de 350.00 + estorno de -350.00 (convenção de sinal do legado)
    expect($service()->calcular('350.00', '0.00', '0.00', '0.00'))
        ->toBe(StatusTaxa::Aberto);
});

it('retorna pago quando o total pago iguala o valor devido', function () use ($service) {
    // devido = 350.00 + 15.50 - 10.00 = 355.50
    expect($service()->calcular('350.00', '10.00', '15.50', '355.50'))
        ->toBe(StatusTaxa::Pago);
});

it('retorna pago quando o total pago excede o valor devido', function () use ($service) {
    expect($service()->calcular('350.00', '0.00', '0.00', '400.00'))
        ->toBe(StatusTaxa::Pago);
});

it('retorna pago_parcial para pagamento abaixo do devido', function () use ($service) {
    expect($service()->calcular('350.00', '0.00', '0.00', '100.00'))
        ->toBe(StatusTaxa::PagoParcial);
});

it('não sofre erro de ponto flutuante em centavos', function () use ($service) {
    // Clássico 0.1 + 0.2: devido = 0.10 + 0.20 - 0.00 = 0.30
    expect($service()->calcular('0.10', '0.00', '0.20', '0.30'))
        ->toBe(StatusTaxa::Pago);

    // Um centavo a menos: parcial, nunca "pago" por arredondamento float
    expect($service()->calcular('0.10', '0.00', '0.20', '0.29'))
        ->toBe(StatusTaxa::PagoParcial);
});

it('trata saldo negativo (estorno maior que pagamento) como aberto', function () use ($service) {
    expect($service()->calcular('350.00', '0.00', '0.00', '-50.00'))
        ->toBe(StatusTaxa::Aberto);
});

it('retorna aberto quando devido é zero e nada foi pago', function () use ($service) {
    expect($service()->calcular('0.00', '0.00', '0.00', '0.00'))
        ->toBe(StatusTaxa::Aberto);
});
