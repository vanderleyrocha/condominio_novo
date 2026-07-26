<?php

declare(strict_types=1);

// Fase 4 da remodelagem — módulo Parâmetros/Índices
// (Ipca → IndiceEconomico, Parametro → Configuracao).

use App\Actions\Financeiro\SalvarIndiceEconomico;
use App\Enums\PapelUsuario;
use App\Enums\TipoIndiceEconomico;
use App\Models\Condominio;
use App\Models\Configuracao;
use App\Models\IndiceEconomico;
use App\Models\User;
use App\Support\ConfiguracoesCondominio;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    ConfiguracoesCondominio::limparCache();
});

it('lê e grava configurações tipadas escopadas pelo condomínio', function () {
    Condominio::factory()->create(['nome' => 'Meu Condomínio']);

    // Defaults de paridade quando a chave não existe
    expect(ConfiguracoesCondominio::taxaMensalidadePadrao())->toBe('150.00')
        ->and(ConfiguracoesCondominio::anoInicialFiltroPagamentos())->toBe(2014)
        ->and(ConfiguracoesCondominio::nomeCondominio())->toBe('Meu Condomínio');

    ConfiguracoesCondominio::set('taxa_mensalidade_padrao', '175.50', 'decimal');
    ConfiguracoesCondominio::setNomeCondominio('Condomínio Renomeado');

    expect(ConfiguracoesCondominio::taxaMensalidadePadrao())->toBe('175.50')
        ->and(ConfiguracoesCondominio::nomeCondominio())->toBe('Condomínio Renomeado')
        ->and(Configuracao::query()->where('chave', 'taxa_mensalidade_padrao')->count())->toBe(1);
});

it('salva índice econômico com unicidade por série', function () {
    $acao = app(SalvarIndiceEconomico::class);

    $ipca = $acao->executar(TipoIndiceEconomico::Ipca, 2026, 1, '0.4200');
    // Mesma competência em OUTRA série é permitida (unique composta inclui tipo)
    $igpm = $acao->executar(TipoIndiceEconomico::Igpm, 2026, 1, '0.5100');

    expect(IndiceEconomico::query()->count())->toBe(2);

    // Edição preserva a linha
    $acao->executar(TipoIndiceEconomico::Ipca, 2026, 1, '0.4300', $ipca);
    expect((string) $ipca->fresh()->indice)->toBe('0.4300')
        ->and(IndiceEconomico::query()->count())->toBe(2);

    // Duplicar a mesma série/competência estoura a unique do banco
    expect(fn () => $acao->executar(TipoIndiceEconomico::Igpm, 2026, 1, '0.9900'))
        ->toThrow(UniqueConstraintViolationException::class);

    expect($igpm)->not->toBeNull();
});

it('restringe índices a admin e configurações a admin/sindico', function () {
    $indice = IndiceEconomico::factory()->create();
    $configuracao = Configuracao::factory()->create();

    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);
    $sindico = User::factory()->create(['papel' => PapelUsuario::Sindico]);
    $proprietario = User::factory()->create(['papel' => PapelUsuario::Proprietario]);

    expect($admin->can('gerenciar', IndiceEconomico::class))->toBeTrue()
        ->and($sindico->can('gerenciar', IndiceEconomico::class))->toBeFalse()
        ->and($admin->can('gerenciar', Configuracao::class))->toBeTrue()
        ->and($sindico->can('gerenciar', Configuracao::class))->toBeTrue()
        ->and($proprietario->can('gerenciar', Configuracao::class))->toBeFalse();

    expect($indice)->not->toBeNull()->and($configuracao)->not->toBeNull();
});

it('renderiza as telas novas para admin', function () {
    Condominio::factory()->create();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    $this->actingAs($admin)->get(route('indices.index'))->assertOk();
    $this->actingAs($admin)->get(route('configuracoes.edit'))->assertOk();
});

it('bloqueia a tela de índices para sindico', function () {
    Condominio::factory()->create();
    $sindico = User::factory()->create(['papel' => PapelUsuario::Sindico]);

    $this->actingAs($sindico)->get(route('indices.index'))->assertForbidden();
    $this->actingAs($sindico)->get(route('configuracoes.edit'))->assertOk();
});
