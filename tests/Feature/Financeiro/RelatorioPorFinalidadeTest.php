<?php

declare(strict_types=1);

// Etapa 5 de docs/migration/05-plano-composicao-taxas.md — as telas de leitura:
// gestão de finalidades, relatório de arrecadação por finalidade, composição na
// edição da taxa e finalidade nos lançamentos.

use App\Actions\Financeiro\EstornarPagamento;
use App\Actions\Financeiro\LancarTaxas;
use App\Actions\Financeiro\RegistrarPagamento;
use App\Enums\FormaPagamento;
use App\Enums\NaturezaLancamento;
use App\Enums\PapelUsuario;
use App\Enums\PapelVinculo;
use App\Livewire\Financeiro\Finalidades\Gestao as GestaoFinalidades;
use App\Livewire\Financeiro\Lancamentos\Listagem as ListagemLancamentos;
use App\Livewire\Financeiro\Relatorios\PorFinalidade;
use App\Livewire\Financeiro\Taxas\EdicaoIndividual;
use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use App\Models\Finalidade;
use App\Models\ItemTaxa;
use App\Models\LancamentoFinanceiro;
use App\Models\PlanoConta;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Models\UnidadePessoa;
use App\Models\User;
use App\Services\RateioPorFinalidadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Ano lançado com composição 100 (custeio) + 50 (pintura) em todas as
 * competências, mais o responsável financeiro para registrar pagamentos.
 */
function cenarioFinalidade(): array
{
    $condominio = Condominio::factory()->create();

    PlanoConta::factory()->receita()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'R-001', 'descricao' => 'Receita de Taxa Condominial',
    ]);
    PlanoConta::factory()->receita()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'R-002', 'descricao' => 'Cobranças Extraordinárias',
    ]);

    $unidade = Unidade::factory()->create(['condominio_id' => $condominio->id, 'identificacao' => 'Casa 01']);
    $vinculo = UnidadePessoa::factory()->create([
        'unidade_id' => $unidade->id, 'papel' => PapelVinculo::Proprietario, 'responsavel_financeiro' => true,
    ]);

    $pintura = Finalidade::factory()->create([
        'condominio_id' => $condominio->id, 'nome' => 'Pintura do prédio', 'meta_valor' => '24000.00',
    ]);

    CobrancaExtraordinaria::factory()->create([
        'condominio_id' => $condominio->id,
        'nome' => 'Taxa para pintura do prédio',
        'finalidade_id' => $pintura->id,
        'valor_total' => '24000.00',
        'valor_por_unidade' => '50.00',
        'vigencia_inicio' => '2030-01-01',
        'vigencia_fim' => '2030-12-31',
    ]);

    app(LancarTaxas::class)->executar(2030, '100.00');

    return compact('condominio', 'unidade', 'pintura') + ['pessoa' => $vinculo->pessoa];
}

it('mostra a arrecadação por finalidade somando taxas e receitas', function () {
    $cenario = cenarioFinalidade();
    $janeiro = TaxaCondominial::query()->where('competencia_mes', 1)->sole();

    // Quitação integral de janeiro: 100 custeio + 50 pintura
    app(RegistrarPagamento::class)->executar(
        $cenario['pessoa'], $cenario['unidade'], '2030-01-10', 'Mensalidade 01', 150.00, [$janeiro->id],
        FormaPagamento::NaoInformado,
    );

    // Rendimento da poupança da pintura (D-05)
    LancamentoFinanceiro::factory()->create([
        'condominio_id' => $cenario['condominio']->id,
        'plano_conta_id' => PlanoConta::query()->where('codigo', 'R-002')->value('id'),
        'finalidade_id' => $cenario['pintura']->id,
        'natureza' => NaturezaLancamento::Receita,
        'descricao' => 'Rendimentos da conta',
        'valor' => '276.41',
        'data_lancamento' => '2030-06-30',
        'data_competencia' => '2030-06-30',
    ]);

    $linhas = app(RateioPorFinalidadeService::class)
        ->arrecadadoPorFinalidade()
        ->mapWithKeys(fn (array $l): array => [$l['finalidade']?->nome ?? 'sem' => $l]);

    expect($linhas['Custeio ordinário']['cobrado'])->toBe('1200.00')
        ->and($linhas['Custeio ordinário']['arrecadado_taxas'])->toBe('100.00')
        ->and($linhas['Pintura do prédio']['cobrado'])->toBe('600.00')
        ->and($linhas['Pintura do prédio']['arrecadado_taxas'])->toBe('50.00')
        ->and($linhas['Pintura do prédio']['receitas'])->toBe('276.41')
        ->and($linhas['Pintura do prédio']['arrecadado'])->toBe('326.41')
        ->and($linhas['Pintura do prédio']['a_receber'])->toBe('550.00');
});

it('em pagamento parcial a contribuição só arrecada depois do custeio', function () {
    $cenario = cenarioFinalidade();
    $janeiro = TaxaCondominial::query()->where('competencia_mes', 1)->sole();

    app(RegistrarPagamento::class)->executar(
        $cenario['pessoa'], $cenario['unidade'], '2030-01-10', 'Parcial', 75.00, [$janeiro->id],
        FormaPagamento::NaoInformado,
    );

    $linhas = app(RateioPorFinalidadeService::class)
        ->arrecadadoPorFinalidade()
        ->mapWithKeys(fn (array $l): array => [$l['finalidade']?->nome ?? 'sem' => $l]);

    expect($linhas['Custeio ordinário']['arrecadado_taxas'])->toBe('75.00')
        ->and($linhas['Pintura do prédio']['arrecadado_taxas'])->toBe('0.00');
});

it('o estorno desfaz a arrecadação da finalidade', function () {
    $cenario = cenarioFinalidade();
    $janeiro = TaxaCondominial::query()->where('competencia_mes', 1)->sole();

    $pagamento = app(RegistrarPagamento::class)->executar(
        $cenario['pessoa'], $cenario['unidade'], '2030-01-10', 'Mensalidade 01', 150.00, [$janeiro->id],
        FormaPagamento::NaoInformado,
    );

    app(EstornarPagamento::class)->executar($pagamento, [$janeiro->id => 150.00]);

    $linhas = app(RateioPorFinalidadeService::class)
        ->arrecadadoPorFinalidade()
        ->mapWithKeys(fn (array $l): array => [$l['finalidade']?->nome ?? 'sem' => $l]);

    expect($linhas['Custeio ordinário']['arrecadado_taxas'])->toBe('0.00')
        ->and($linhas['Pintura do prédio']['arrecadado_taxas'])->toBe('0.00');
});

it('congela a leitura numa data de posição', function () {
    $cenario = cenarioFinalidade();
    $janeiro = TaxaCondominial::query()->where('competencia_mes', 1)->sole();

    app(RegistrarPagamento::class)->executar(
        $cenario['pessoa'], $cenario['unidade'], '2030-06-10', 'Mensalidade 01', 150.00, [$janeiro->id],
        FormaPagamento::NaoInformado,
    );

    $service = app(RateioPorFinalidadeService::class);

    $antes = $service->arrecadadoPorFinalidade('2030-05-31')
        ->firstWhere(fn (array $l): bool => $l['finalidade']?->nome === 'Pintura do prédio');
    $depois = $service->arrecadadoPorFinalidade('2030-12-31')
        ->firstWhere(fn (array $l): bool => $l['finalidade']?->nome === 'Pintura do prédio');

    expect($antes['arrecadado_taxas'])->toBe('0.00')
        ->and($depois['arrecadado_taxas'])->toBe('50.00');
});

it('renderiza o relatório por finalidade com a nota da ordem de quitação', function () {
    cenarioFinalidade();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    Livewire::actingAs($admin)->test(PorFinalidade::class)
        ->assertOk()
        ->assertSee('Arrecadação por finalidade')
        ->assertSee('Pintura do prédio')
        ->assertSee('Custeio ordinário')
        ->assertSee('a taxa condominial primeiro, as contribuições depois');
});

it('renderiza a gestão de finalidades e cadastra uma nova', function () {
    cenarioFinalidade();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    Livewire::actingAs($admin)->test(GestaoFinalidades::class)
        ->assertOk()
        ->assertSee('Pintura do prédio')
        ->call('nova')
        ->set('formNome', 'Troca do portão')
        ->set('formMeta', '8.000,00')
        ->call('salvar')
        ->assertHasNoErrors();

    $criada = Finalidade::query()->where('nome', 'Troca do portão')->sole();

    expect((string) $criada->meta_valor)->toBe('8000.00')
        ->and($criada->ativa)->toBeTrue();
});

it('edita a composição pela tela da taxa e o valor devido acompanha', function () {
    cenarioFinalidade();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);
    $taxa = TaxaCondominial::query()->where('competencia_mes', 1)->sole();

    Livewire::actingAs($admin)->test(EdicaoIndividual::class, ['taxa' => $taxa])
        ->assertOk()
        ->assertSee('Composição da mensalidade')
        ->assertSee('Taxa para pintura do prédio')
        ->call('novoItem')
        ->set('itemDescricao', 'Fundo de reserva')
        ->set('itemValor', '25,00')
        ->call('salvarItem')
        ->assertHasNoErrors();

    expect((string) $taxa->fresh()->valor_original)->toBe('175.00')
        ->and($taxa->fresh()->itens)->toHaveCount(3);
});

it('recusa item duplicado na mesma competência', function () {
    cenarioFinalidade();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);
    $taxa = TaxaCondominial::query()->where('competencia_mes', 1)->sole();

    Livewire::actingAs($admin)->test(EdicaoIndividual::class, ['taxa' => $taxa])
        ->call('novoItem')
        ->set('itemDescricao', 'Taxa condominial') // já existe
        ->set('itemValor', '10,00')
        ->call('salvarItem')
        ->assertHasErrors('itemDescricao');

    expect((string) $taxa->fresh()->valor_original)->toBe('150.00');
});

it('remove item pela tela e o valor devido cai', function () {
    cenarioFinalidade();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);
    $taxa = TaxaCondominial::query()->where('competencia_mes', 1)->sole();
    $itemPintura = $taxa->itens[1];

    Livewire::actingAs($admin)->test(EdicaoIndividual::class, ['taxa' => $taxa])
        ->call('removerItem', $itemPintura->id)
        ->assertHasNoErrors();

    expect((string) $taxa->fresh()->valor_original)->toBe('100.00')
        ->and(ItemTaxa::query()->withTrashed()->find($itemPintura->id)->trashed())->toBeTrue();
});

it('filtra lançamentos por finalidade', function () {
    $cenario = cenarioFinalidade();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);
    $planoReceita = PlanoConta::query()->where('codigo', 'R-002')->value('id');

    LancamentoFinanceiro::factory()->create([
        'condominio_id' => $cenario['condominio']->id, 'plano_conta_id' => $planoReceita,
        'finalidade_id' => $cenario['pintura']->id, 'natureza' => NaturezaLancamento::Receita,
        'descricao' => 'Rendimentos da conta', 'valor' => '276.41',
        'data_lancamento' => '2030-06-30', 'data_competencia' => '2030-06-30',
    ]);
    LancamentoFinanceiro::factory()->create([
        'condominio_id' => $cenario['condominio']->id, 'plano_conta_id' => $planoReceita,
        'finalidade_id' => null, 'natureza' => NaturezaLancamento::Receita,
        'descricao' => 'Receita avulsa sem destinação', 'valor' => '10.00',
        'data_lancamento' => '2030-06-30', 'data_competencia' => '2030-06-30',
    ]);

    Livewire::actingAs($admin)->test(ListagemLancamentos::class)
        ->set('ano', '2030')
        ->assertSee('Rendimentos da conta')
        ->assertSee('Receita avulsa sem destinação')
        ->set('finalidade', $cenario['pintura']->id)
        ->assertSee('Rendimentos da conta')
        ->assertDontSee('Receita avulsa sem destinação');
});

it('grava a finalidade ao criar um lançamento pela tela', function () {
    $cenario = cenarioFinalidade();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    Livewire::actingAs($admin)->test(ListagemLancamentos::class)
        ->call('novoLancamento')
        ->set('formNatureza', 'receita')
        ->set('formPlanoId', PlanoConta::query()->where('codigo', 'R-002')->value('id'))
        ->set('formData', '2030-07-01')
        ->set('formDescricao', 'Doação para a pintura')
        ->set('formValor', '500,00')
        ->set('formFinalidadeId', $cenario['pintura']->id)
        ->call('salvar')
        ->assertHasNoErrors();

    $lancamento = LancamentoFinanceiro::query()->where('descricao', 'Doação para a pintura')->sole();

    expect($lancamento->finalidade->nome)->toBe('Pintura do prédio');
});

it('abre as rotas novas para admin autenticado', function () {
    cenarioFinalidade();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    $this->actingAs($admin)->get(route('finalidades.index'))->assertOk();
    $this->actingAs($admin)->get(route('relatorios.por-finalidade'))->assertOk();
});
