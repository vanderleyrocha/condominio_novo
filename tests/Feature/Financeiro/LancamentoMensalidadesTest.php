<?php

declare(strict_types=1);

// PAR-01 — parity_tests/01-lancamento-mensalidades.feature

use App\Actions\Financeiro\LancarMensalidades;
use App\Models\Imovel;
use App\Models\Mensalidade;
use App\Models\Parametro;
use App\Models\Proprietario;
use App\Support\ParametrosCondominio;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function criarImoveis(int $quantidade): void
{
    $proprietario = Proprietario::query()->create([
        'nome' => 'Proprietário Teste',
        'cpf' => '39053344705',
        'telefone' => '68999990000',
        'responsavel_pagamento' => 'proprietario',
    ]);

    for ($i = 1; $i <= $quantidade; $i++) {
        Imovel::query()->create(['proprietario_id' => $proprietario->id, 'nome' => "Apto {$i}"]);
    }
}

beforeEach(function () {
    Parametro::query()->create(['chave' => 'taxa_mensalidade_padrao', 'valor' => '150.00']);
    ParametrosCondominio::limparCache();
});

test('lançar mensalidades de um ano para todos os imóveis (paridade RN-01/03/05)', function () {
    criarImoveis(10);

    $criadas = app(LancarMensalidades::class)->executar(2027);

    expect($criadas)->toBe(120)
        ->and(Mensalidade::query()->where('ano', 2027)->count())->toBe(120);

    $todas = Mensalidade::query()->where('ano', 2027)->get();
    expect($todas->every(fn ($m) => (float) $m->valor === 150.00))->toBeTrue()
        ->and($todas->every(fn ($m) => $m->contabilizado === true))->toBeTrue();

    // Vencimento no último dia do respectivo mês
    foreach ($todas as $m) {
        expect($m->vencimento->format('Y-m-d'))
            ->toBe($m->vencimento->copy()->endOfMonth()->format('Y-m-d'));
    }
});

test('relançar o mesmo ano é bloqueado (EX-01 — divergência deliberada)', function () {
    criarImoveis(2);
    app(LancarMensalidades::class)->executar(2027);

    expect(fn () => app(LancarMensalidades::class)->executar(2027))
        ->toThrow(DomainException::class, 'As mensalidades do ano 2027 já foram lançadas.');

    expect(Mensalidade::query()->where('ano', 2027)->count())->toBe(24);
});

test('lançamento sem imóveis é recusado', function () {
    expect(fn () => app(LancarMensalidades::class)->executar(2028))
        ->toThrow(DomainException::class);

    expect(Mensalidade::query()->where('ano', 2028)->count())->toBe(0);
});
