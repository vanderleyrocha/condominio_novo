<?php

declare(strict_types=1);

// Fase 4 da remodelagem — módulo Cadastros (Pessoas/Unidades/Vínculos).

use App\Actions\Cadastros\EncerrarVinculo;
use App\Actions\Cadastros\ExcluirPessoa;
use App\Actions\Cadastros\ExcluirUnidade;
use App\Actions\Cadastros\SalvarPessoa;
use App\Actions\Cadastros\SalvarUnidade;
use App\Actions\Cadastros\VincularPessoa;
use App\Enums\PapelUsuario;
use App\Enums\PapelVinculo;
use App\Models\Condominio;
use App\Models\Pagamento;
use App\Models\Pessoa;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Models\UnidadePessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('salva pessoa normalizando o documento', function () {
    $pessoa = app(SalvarPessoa::class)->executar([
        'nome' => 'Maria Silva', 'cpf_cnpj' => '390.533.447-05', 'tipo' => 'fisica',
    ]);

    expect($pessoa->cpf_cnpj)->toBe('39053344705');
});

it('bloqueia exclusão de pessoa com vínculo, pagamento ou conta', function () {
    $vinculo = UnidadePessoa::factory()->create();

    expect(fn () => app(ExcluirPessoa::class)->executar($vinculo->pessoa))
        ->toThrow(DomainException::class, 'vínculos com unidades');

    $pagadora = Pessoa::factory()->create();
    Pagamento::factory()->create(['pessoa_id' => $pagadora->id]);
    expect(fn () => app(ExcluirPessoa::class)->executar($pagadora))
        ->toThrow(DomainException::class, 'pagamentos registrados');

    $comConta = Pessoa::factory()->create();
    User::factory()->create(['pessoa_id' => $comConta->id]);
    expect(fn () => app(ExcluirPessoa::class)->executar($comConta))
        ->toThrow(DomainException::class, 'conta de acesso');
});

it('exclui pessoa livre com soft delete', function () {
    $pessoa = Pessoa::factory()->create();

    app(ExcluirPessoa::class)->executar($pessoa);

    expect(Pessoa::query()->count())->toBe(0)
        ->and(Pessoa::withTrashed()->count())->toBe(1);
});

it('cria unidade no condomínio único', function () {
    Condominio::factory()->create();

    $unidade = app(SalvarUnidade::class)->executar(['identificacao' => 'Casa 99']);

    expect($unidade->condominio_id)->toBe(Condominio::query()->value('id'));
});

it('bloqueia exclusão de unidade com taxas', function () {
    $taxa = TaxaCondominial::factory()->create();

    expect(fn () => app(ExcluirUnidade::class)->executar($taxa->unidade))
        ->toThrow(DomainException::class, 'taxas condominiais lançadas');
});

it('transfere a responsabilidade financeira ao vincular novo responsável', function () {
    $vinculoOriginal = UnidadePessoa::factory()->create(['responsavel_financeiro' => true]);
    $novaPessoa = Pessoa::factory()->create();

    $novoVinculo = app(VincularPessoa::class)->executar(
        $vinculoOriginal->unidade,
        $novaPessoa,
        PapelVinculo::Inquilino,
        true,
        now()->toDateString(),
    );

    expect($novoVinculo->responsavel_financeiro)->toBeTrue()
        ->and($vinculoOriginal->fresh()->responsavel_financeiro)->toBeFalse()
        ->and($vinculoOriginal->fresh()->data_fim)->toBeNull(); // segue vigente, só não é mais responsável

    // Invariante: no máximo 1 responsável vigente por unidade
    expect($vinculoOriginal->unidade->vinculos()->where('responsavel_financeiro', true)->whereNull('data_fim')->count())->toBe(1);
});

it('rejeita vínculo duplicado vigente (mesma pessoa e papel)', function () {
    $vinculo = UnidadePessoa::factory()->create();

    expect(fn () => app(VincularPessoa::class)->executar(
        $vinculo->unidade, $vinculo->pessoa, PapelVinculo::Proprietario, false, now()->toDateString(),
    ))->toThrow(DomainException::class);
});

it('encerra vínculo preservando o histórico', function () {
    $vinculo = UnidadePessoa::factory()->create(['responsavel_financeiro' => true]);

    app(EncerrarVinculo::class)->executar($vinculo);

    $vinculo->refresh();
    expect($vinculo->data_fim)->not->toBeNull()
        ->and($vinculo->responsavel_financeiro)->toBeFalse();

    expect(fn () => app(EncerrarVinculo::class)->executar($vinculo))
        ->toThrow(DomainException::class, 'já está encerrado');
});

it('autoriza admin e sindico a gerir cadastros, mas não proprietario', function () {
    $pessoa = Pessoa::factory()->create();
    $unidade = Unidade::factory()->create();

    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);
    $sindico = User::factory()->create(['papel' => PapelUsuario::Sindico]);
    $proprietario = User::factory()->create(['papel' => PapelUsuario::Proprietario]);

    expect($admin->can('update', $pessoa))->toBeTrue()
        ->and($sindico->can('update', $pessoa))->toBeTrue()
        ->and($proprietario->can('update', $pessoa))->toBeFalse()
        ->and($admin->can('gerirVinculos', $unidade))->toBeTrue()
        ->and($sindico->can('gerirVinculos', $unidade))->toBeTrue()
        ->and($proprietario->can('gerirVinculos', $unidade))->toBeFalse();
});

it('renderiza as telas novas para admin', function () {
    Condominio::factory()->create();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    $this->actingAs($admin)->get(route('pessoas.index'))->assertOk();
    $this->actingAs($admin)->get(route('pessoas.create'))->assertOk();
    $this->actingAs($admin)->get(route('unidades.index'))->assertOk();
});
