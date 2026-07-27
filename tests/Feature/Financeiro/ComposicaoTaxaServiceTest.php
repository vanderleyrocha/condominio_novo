<?php

declare(strict_types=1);

// Etapa 2 de docs/migration/05-plano-composicao-taxas.md: o ComposicaoTaxaService
// é o único ponto de escrita de valor_original (cache = SUM(itens.valor)).

use App\Enums\StatusTaxa;
use App\Models\Condominio;
use App\Models\Finalidade;
use App\Models\PlanoConta;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Services\ComposicaoTaxaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cenarioComposicao(string $valorOriginal = '0.00'): array
{
    $condominio = Condominio::factory()->create();
    $unidade = Unidade::factory()->create(['condominio_id' => $condominio->id]);
    $plano = PlanoConta::factory()->receita()->create(['condominio_id' => $condominio->id]);

    $taxa = TaxaCondominial::factory()->create([
        'unidade_id' => $unidade->id,
        'competencia_mes' => 1,
        'competencia_ano' => 2030,
        'valor_original' => $valorOriginal,
        'valor_desconto' => '0.00',
        'valor_acrescimo' => '0.00',
    ]);

    return [$taxa, $plano, $condominio];
}

it('soma os itens em BCMath sem erro de ponto flutuante', function () {
    $service = app(ComposicaoTaxaService::class);

    expect($service->somar(['0.10', '0.20']))->toBe('0.30')
        ->and($service->somar(['100.00', '50.00']))->toBe('150.00')
        ->and($service->somar([]))->toBe('0.00');
});

it('grava valor_original como a soma dos itens ao adicionar', function () {
    [$taxa, $plano] = cenarioComposicao();
    $service = app(ComposicaoTaxaService::class);

    $service->adicionarItem($taxa, [
        'plano_conta_id' => $plano->id, 'descricao' => 'Taxa condominial', 'valor' => '100.00', 'ordem' => 0,
    ]);
    $service->adicionarItem($taxa, [
        'plano_conta_id' => $plano->id, 'descricao' => 'Taxa para pintura do prédio', 'valor' => '50.00', 'ordem' => 1,
    ]);

    expect((string) $taxa->fresh()->valor_original)->toBe('150.00')
        ->and($taxa->fresh()->itens)->toHaveCount(2);
});

it('atribui a próxima ordem automaticamente', function () {
    [$taxa, $plano] = cenarioComposicao();
    $service = app(ComposicaoTaxaService::class);

    $service->adicionarItem($taxa, ['plano_conta_id' => $plano->id, 'descricao' => 'A', 'valor' => '10.00']);
    $segundo = $service->adicionarItem($taxa, ['plano_conta_id' => $plano->id, 'descricao' => 'B', 'valor' => '10.00']);

    expect($segundo->ordem)->toBe(1);
});

it('recalcula valor_original e status ao atualizar um item', function () {
    [$taxa, $plano] = cenarioComposicao();
    $service = app(ComposicaoTaxaService::class);

    $item = $service->adicionarItem($taxa, [
        'plano_conta_id' => $plano->id, 'descricao' => 'Taxa condominial', 'valor' => '100.00',
    ]);

    $service->atualizarItem($item, ['valor' => '120.00']);

    expect((string) $taxa->fresh()->valor_original)->toBe('120.00')
        ->and($taxa->fresh()->status)->toBe(StatusTaxa::Aberto);
});

it('recalcula valor_original ao remover um item', function () {
    [$taxa, $plano] = cenarioComposicao();
    $service = app(ComposicaoTaxaService::class);

    $service->adicionarItem($taxa, ['plano_conta_id' => $plano->id, 'descricao' => 'Ordinária', 'valor' => '100.00']);
    $pintura = $service->adicionarItem($taxa, ['plano_conta_id' => $plano->id, 'descricao' => 'Pintura', 'valor' => '50.00']);

    $service->removerItem($pintura);

    expect((string) $taxa->fresh()->valor_original)->toBe('100.00')
        ->and($taxa->fresh()->itens)->toHaveCount(1);
});

it('recusa remover o último item da taxa', function () {
    [$taxa, $plano] = cenarioComposicao();
    $service = app(ComposicaoTaxaService::class);

    $unico = $service->adicionarItem($taxa, ['plano_conta_id' => $plano->id, 'descricao' => 'Ordinária', 'valor' => '100.00']);

    expect(fn () => $service->removerItem($unico))
        ->toThrow(DomainException::class, 'ao menos um item');
});

it('aceita item de valor zero mantendo a invariante', function () {
    [$taxa, $plano] = cenarioComposicao();
    $service = app(ComposicaoTaxaService::class);

    $service->adicionarItem($taxa, ['plano_conta_id' => $plano->id, 'descricao' => 'Competência isenta', 'valor' => '0.00']);

    expect((string) $taxa->fresh()->valor_original)->toBe('0.00');
});

it('deixa intacta a taxa que ainda não foi decomposta', function () {
    // Segurança do backfill: recalcular uma taxa sem itens não pode zerá-la
    [$taxa] = cenarioComposicao('150.00');

    app(ComposicaoTaxaService::class)->recalcular($taxa);

    expect((string) $taxa->fresh()->valor_original)->toBe('150.00');
});

it('vincula a finalidade ao item', function () {
    [$taxa, $plano, $condominio] = cenarioComposicao();
    $finalidade = Finalidade::factory()->create(['condominio_id' => $condominio->id, 'nome' => 'Pintura do prédio']);

    $item = app(ComposicaoTaxaService::class)->adicionarItem($taxa, [
        'plano_conta_id' => $plano->id,
        'finalidade_id' => $finalidade->id,
        'descricao' => 'Taxa para pintura do prédio',
        'valor' => '50.00',
    ]);

    expect($item->finalidade->nome)->toBe('Pintura do prédio');
});
