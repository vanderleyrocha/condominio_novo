<?php

declare(strict_types=1);

// Segregação do saldo por destinação: uma finalidade marcada como `restrita`
// é dinheiro carimbado — o saldo dela sai do "disponível para custeio
// ordinário" no Resumo financeiro, em vez de dar a impressão de caixa livre.

use App\Actions\Financeiro\LancarTaxas;
use App\Actions\Financeiro\RegistrarPagamento;
use App\Actions\Financeiro\SalvarFinalidade;
use App\Enums\FormaPagamento;
use App\Enums\NaturezaLancamento;
use App\Enums\PapelUsuario;
use App\Enums\PapelVinculo;
use App\Livewire\Financeiro\Finalidades\Gestao as GestaoFinalidades;
use App\Livewire\Financeiro\Resumo\Index as ResumoIndex;
use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use App\Models\Finalidade;
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
 * Mesmo cenário do relatório por finalidade (100 custeio + 50 pintura em 2030),
 * com a pintura marcada como VINCULADA e janeiro quitado integralmente:
 * caixa de 150,00, dos quais 50,00 são carimbados para a pintura.
 */
function cenarioVinculado(): array
{
    $condominio = Condominio::factory()->create();

    PlanoConta::factory()->receita()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'R-001', 'descricao' => 'Receita de Taxa Condominial',
    ]);
    PlanoConta::factory()->receita()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'R-002', 'descricao' => 'Cobranças Extraordinárias',
    ]);
    $planoDespesa = PlanoConta::factory()->despesa()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'D-001', 'descricao' => 'Manutenção',
    ]);

    $unidade = Unidade::factory()->create(['condominio_id' => $condominio->id, 'identificacao' => 'Casa 01']);
    $vinculo = UnidadePessoa::factory()->create([
        'unidade_id' => $unidade->id, 'papel' => PapelVinculo::Proprietario, 'responsavel_financeiro' => true,
    ]);

    $pintura = Finalidade::factory()->restrita()->create([
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

    $janeiro = TaxaCondominial::query()->where('competencia_mes', 1)->sole();

    app(RegistrarPagamento::class)->executar(
        $vinculo->pessoa, $unidade, '2030-01-10', 'Mensalidade 01', 150.00, [$janeiro->id],
        FormaPagamento::NaoInformado,
    );

    return compact('condominio', 'unidade', 'pintura', 'planoDespesa');
}

/** Despesa lançada contra uma finalidade — consome o fundo dela. */
function despesaNaFinalidade(array $cenario, string $valor, ?int $finalidadeId): LancamentoFinanceiro
{
    return LancamentoFinanceiro::factory()->create([
        'condominio_id' => $cenario['condominio']->id,
        'plano_conta_id' => $cenario['planoDespesa']->id,
        'finalidade_id' => $finalidadeId,
        'natureza' => NaturezaLancamento::Despesa,
        'descricao' => 'Gasto',
        'valor' => $valor,
        'data_lancamento' => '2030-03-01',
        'data_competencia' => '2030-03-01',
    ]);
}

it('desconta as despesas da finalidade do saldo do fundo', function () {
    $cenario = cenarioVinculado();
    despesaNaFinalidade($cenario, '20.00', $cenario['pintura']->id);

    $linha = app(RateioPorFinalidadeService::class)
        ->arrecadadoPorFinalidade()
        ->firstWhere(fn (array $l): bool => $l['finalidade']?->nome === 'Pintura do prédio');

    expect($linha['arrecadado'])->toBe('50.00')
        ->and($linha['gasto'])->toBe('20.00')
        ->and($linha['saldo'])->toBe('30.00');
});

it('soma como vinculado apenas o saldo das finalidades restritas', function () {
    cenarioVinculado();
    $service = app(RateioPorFinalidadeService::class);

    $vinculadas = $service->vinculadoPorFinalidade();

    expect($vinculadas)->toHaveCount(1)
        ->and($vinculadas->first()['finalidade']->nome)->toBe('Pintura do prédio')
        ->and($service->somarSaldoVinculado($vinculadas))->toBe('50.00');

    // O custeio ordinário arrecadou 100,00 e não é restrito — fica de fora
    expect($vinculadas->pluck('finalidade.nome'))->not->toContain('Custeio ordinário');
});

it('o saldo negativo de um fundo não vira crédito no total vinculado', function () {
    $cenario = cenarioVinculado();
    despesaNaFinalidade($cenario, '80.00', $cenario['pintura']->id); // gastou mais do que juntou

    $service = app(RateioPorFinalidadeService::class);
    $vinculadas = $service->vinculadoPorFinalidade();

    expect($vinculadas->first()['saldo'])->toBe('-30.00')
        ->and($service->somarSaldoVinculado($vinculadas))->toBe('0.00');
});

it('o resumo separa o vinculado do disponível para custeio', function () {
    cenarioVinculado();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    Livewire::actingAs($admin)->test(ResumoIndex::class)
        ->assertOk()
        ->assertViewHas('saldoFinal', 150.0)
        ->assertViewHas('saldoVinculado', 50.0)
        ->assertViewHas('disponivelCusteio', 100.0)
        ->assertSee('Recursos vinculados a finalidades')
        ->assertSee('Disponível para custeio');
});

it('a despesa carimbada sai do caixa sem reduzir o disponível para custeio', function () {
    $cenario = cenarioVinculado();
    despesaNaFinalidade($cenario, '20.00', $cenario['pintura']->id);
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    // Caixa cai para 130,00 e o fundo da pintura para 30,00 — o custeio
    // continua com os mesmos 100,00: o gasto era carimbado.
    Livewire::actingAs($admin)->test(ResumoIndex::class)
        ->assertViewHas('saldoFinal', 130.0)
        ->assertViewHas('saldoVinculado', 30.0)
        ->assertViewHas('disponivelCusteio', 100.0);
});

it('avisa quando o custeio corrente consome recursos vinculados', function () {
    $cenario = cenarioVinculado();
    despesaNaFinalidade($cenario, '120.00', null); // despesa ordinária maior que o custeio arrecadado
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    // Caixa 30,00, dos quais 50,00 são da pintura → custeio negativo em 20,00
    Livewire::actingAs($admin)->test(ResumoIndex::class)
        ->assertViewHas('saldoVinculado', 50.0)
        ->assertViewHas('disponivelCusteio', -20.0)
        ->assertSee('está usando');
});

it('sem finalidade vinculada o resumo mantém o saldo final único', function () {
    $cenario = cenarioVinculado();
    $cenario['pintura']->update(['restrita' => false]);
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    Livewire::actingAs($admin)->test(ResumoIndex::class)
        ->assertViewHas('saldoVinculado', 0.0)
        ->assertSee('Saldo final')
        ->assertDontSee('Recursos vinculados a finalidades');
});

it('o PDF do resumo renderiza com a segregação do saldo', function () {
    cenarioVinculado();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    $this->actingAs($admin)->get(route('pdf.resumo'))->assertOk();
});

it('marca e desmarca a finalidade como vinculada pela tela', function () {
    $cenario = cenarioVinculado();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    Livewire::actingAs($admin)->test(GestaoFinalidades::class)
        ->assertOk()
        ->assertSee('Vinculada')
        ->call('editar', $cenario['pintura']->id)
        ->assertSet('formRestrita', true)
        ->set('formRestrita', false)
        ->call('salvar')
        ->assertHasNoErrors();

    expect($cenario['pintura']->fresh()->restrita)->toBeFalse();

    Livewire::actingAs($admin)->test(GestaoFinalidades::class)
        ->call('nova')
        ->set('formNome', 'Troca do portão')
        ->set('formRestrita', true)
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Finalidade::query()->where('nome', 'Troca do portão')->sole()->restrita)->toBeTrue();
});

it('a finalidade nasce não vinculada por padrão', function () {
    $condominio = Condominio::factory()->create();

    $finalidade = app(SalvarFinalidade::class)
        ->executar(['nome' => 'Custeio ordinário'], null);

    expect($finalidade->fresh()->restrita)->toBeFalse()
        ->and($finalidade->condominio_id)->toBe($condominio->id);
});
