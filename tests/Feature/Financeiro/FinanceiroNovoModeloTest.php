<?php

declare(strict_types=1);

// Fase 4 da remodelagem — módulos financeiros sobre o schema novo:
// taxas (lançamento/grade), pagamentos (FIFO/estorno) e lançamentos.

use App\Actions\Financeiro\EstornarPagamento;
use App\Actions\Financeiro\LancarTaxas;
use App\Actions\Financeiro\PagarViaGrade;
use App\Actions\Financeiro\RegistrarPagamento;
use App\Actions\Financeiro\SalvarLancamento;
use App\Enums\PapelUsuario;
use App\Enums\PapelVinculo;
use App\Enums\StatusTaxa;
use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use App\Models\Pagamento;
use App\Models\Pessoa;
use App\Models\PlanoConta;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Models\UnidadePessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cenarioUnidadeComResponsavel(): array
{
    $unidade = Unidade::factory()->create();
    $vinculo = UnidadePessoa::factory()->create([
        'unidade_id' => $unidade->id,
        'papel' => PapelVinculo::Proprietario,
        'responsavel_financeiro' => true,
    ]);

    return [$unidade, $vinculo->pessoa];
}

it('lança 12 taxas por unidade e bloqueia relançamento do ano', function () {
    $condominio = Condominio::factory()->create();
    Unidade::factory()->count(2)->create();

    // A composição da taxa exige o plano de contas da receita ordinária (R-001).
    PlanoConta::factory()->receita()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'R-001', 'descricao' => 'Receita de Taxa Condominial',
    ]);

    $quantidade = app(LancarTaxas::class)->executar(2030, '200.00');

    expect($quantidade)->toBe(24)
        ->and(TaxaCondominial::query()->where('competencia_ano', 2030)->count())->toBe(24);

    expect(fn () => app(LancarTaxas::class)->executar(2030))
        ->toThrow(DomainException::class, 'já foram lançadas');
});

it('registra pagamento com distribuição FIFO e recalcula status', function () {
    [$unidade, $pessoa] = cenarioUnidadeComResponsavel();

    $t1 = TaxaCondominial::factory()->create([
        'unidade_id' => $unidade->id, 'competencia_mes' => 1, 'competencia_ano' => 2030,
        'valor_original' => '100.00',
    ]);
    $t2 = TaxaCondominial::factory()->create([
        'unidade_id' => $unidade->id, 'competencia_mes' => 2, 'competencia_ano' => 2030,
        'valor_original' => '100.00',
    ]);

    // 150 → quita t1 (100) e t2 parcial (50); excedente não redistribuído (RN-12)
    $pagamento = app(RegistrarPagamento::class)->executar(
        $pessoa, $unidade, '2030-01-05', 'Pg FIFO', 150.00, [$t1->id, $t2->id],
    );

    expect($t1->fresh()->status)->toBe(StatusTaxa::Pago)
        ->and($t2->fresh()->status)->toBe(StatusTaxa::PagoParcial)
        ->and((float) $pagamento->pagamentoTaxas()->sum('valor_aplicado'))->toBe(150.0);
});

it('rejeita pagamento de pessoa sem vínculo vigente com a unidade', function () {
    [$unidade] = cenarioUnidadeComResponsavel();
    $estranha = Pessoa::factory()->create();

    expect(fn () => app(RegistrarPagamento::class)->executar(
        $estranha, $unidade, '2030-01-05', 'Pg inválido', 100.00, [],
    ))->toThrow(DomainException::class, 'vínculo vigente');
});

it('estorna com tetos e reabre a taxa; segundo estorno é bloqueado', function () {
    [$unidade, $pessoa] = cenarioUnidadeComResponsavel();
    $taxa = TaxaCondominial::factory()->create(['unidade_id' => $unidade->id, 'valor_original' => '100.00']);

    $pagamento = app(RegistrarPagamento::class)->executar(
        $pessoa, $unidade, '2030-01-05', 'Pg', 100.00, [$taxa->id],
    );
    expect($taxa->fresh()->status)->toBe(StatusTaxa::Pago);

    // Teto 1: estorno acima do aplicado é rejeitado
    expect(fn () => app(EstornarPagamento::class)->executar($pagamento, [$taxa->id => 150.00]))
        ->toThrow(DomainException::class, 'maior que o valor pago');

    $estorno = app(EstornarPagamento::class)->executar($pagamento->fresh(), [$taxa->id => 100.00]);

    expect((float) $estorno->valor_total)->toBe(-100.0)
        ->and($estorno->estorno_de_id)->toBe($pagamento->id)
        ->and($taxa->fresh()->status)->toBe(StatusTaxa::Aberto);

    expect(fn () => app(EstornarPagamento::class)->executar($pagamento->fresh(), [$taxa->id => 10.00]))
        ->toThrow(DomainException::class, 'já foi estornado');
});

it('grade anual gera pagamento real pelo delta e ajuste negativo na redução', function () {
    [$unidade] = cenarioUnidadeComResponsavel();
    $taxa = TaxaCondominial::factory()->create(['unidade_id' => $unidade->id, 'valor_original' => '100.00']);
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    // Célula vai de 0 → 100: cria pagamento de 100
    app(PagarViaGrade::class)->executar([$taxa->id => '100.00'], $admin);
    expect($taxa->fresh()->status)->toBe(StatusTaxa::Pago)
        ->and(Pagamento::query()->count())->toBe(1);

    // Célula 100 → 40: cria ajuste de -60
    app(PagarViaGrade::class)->executar([$taxa->id => '40.00'], $admin);
    expect($taxa->fresh()->status)->toBe(StatusTaxa::PagoParcial)
        ->and((float) $taxa->pagamentoTaxas()->sum('valor_aplicado'))->toBe(40.0)
        ->and((float) Pagamento::query()->min('valor_total'))->toBe(-60.0);

    // Célula sem mudança: nada gravado (persistência seletiva)
    expect(app(PagarViaGrade::class)->executar([$taxa->id => '40.00'], $admin))->toBe(0);
});

it('salva lançamento de receita com origem em cobrança extraordinária', function () {
    Condominio::factory()->create();
    $plano = PlanoConta::factory()->receita()->create();
    $cobranca = CobrancaExtraordinaria::factory()->create();

    $lancamento = app(SalvarLancamento::class)->executar([
        'plano_conta_id' => $plano->id, 'natureza' => 'receita', 'data' => '2030-01-10',
        'descricao' => 'Rateio', 'valor' => '500.00', 'contabilizado' => true,
        'cobranca_extraordinaria_id' => $cobranca->id,
    ]);

    expect($lancamento->origem_type)->toBe(CobrancaExtraordinaria::class)
        ->and((int) $lancamento->origem_id)->toBe($cobranca->id)
        ->and($lancamento->data_competencia->toDateString())->toBe('2030-01-10');
});

it('aplica a matriz de papéis: estorno é admin-only; sindico gere taxas', function () {
    $taxa = TaxaCondominial::factory()->create();
    $pagamento = Pagamento::factory()->create();

    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);
    $sindico = User::factory()->create(['papel' => PapelUsuario::Sindico]);
    $proprietario = User::factory()->create(['papel' => PapelUsuario::Proprietario]);

    expect($admin->can('estornar', $pagamento))->toBeTrue()
        ->and($sindico->can('estornar', $pagamento))->toBeFalse()
        ->and($sindico->can('update', $taxa))->toBeTrue()
        ->and($sindico->can('gerenciarContabilizado', TaxaCondominial::class))->toBeFalse()
        ->and($admin->can('gerenciarContabilizado', TaxaCondominial::class))->toBeTrue()
        ->and($proprietario->can('update', $taxa))->toBeFalse()
        ->and($proprietario->can('create', Pagamento::class))->toBeFalse();
});

it('renderiza as telas novas do financeiro para admin', function () {
    Condominio::factory()->create();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    foreach (['taxas.index', 'taxas.lancar', 'pagamentos.index', 'pagamentos.create',
        'lancamentos.index', 'inadimplencia.index', 'resumo.index', 'resumo.intervalo',
        'painel', 'cobrancas-extraordinarias.index'] as $rota) {
        $this->actingAs($admin)->get(route($rota))->assertOk();
    }
});
