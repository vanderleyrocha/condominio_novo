<?php

declare(strict_types=1);

// Etapa 4 de docs/migration/05-plano-composicao-taxas.md: a taxa nasce COMPOSTA.
// Guarda central deste arquivo: a taxa ordinária vale 100,00 e as contribuições
// recorrentes são somadas por cima — manter a configuração em 150,00 dobraria a
// cobrança (risco listado na §6 do plano).

use App\Actions\Financeiro\AplicarCobrancaEmTaxas;
use App\Actions\Financeiro\LancarTaxas;
use App\Actions\Financeiro\RegistrarPagamento;
use App\Actions\Financeiro\RemoverItemTaxa;
use App\Actions\Financeiro\SalvarItemTaxa;
use App\Enums\FormaPagamento;
use App\Enums\PapelUsuario;
use App\Enums\PapelVinculo;
use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use App\Models\Finalidade;
use App\Models\PlanoConta;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Models\UnidadePessoa;
use App\Models\User;
use App\Support\ConfiguracoesCondominio;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cenarioLancamento(bool $comCampanha = true): array
{
    ConfiguracoesCondominio::limparCache();

    $condominio = Condominio::factory()->create();

    PlanoConta::factory()->receita()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'R-001', 'descricao' => 'Receita de Taxa Condominial',
    ]);
    PlanoConta::factory()->receita()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'R-002', 'descricao' => 'Cobranças Extraordinárias',
    ]);

    $unidade = Unidade::factory()->create(['condominio_id' => $condominio->id]);

    $campanha = null;

    if ($comCampanha) {
        $finalidade = Finalidade::factory()->create([
            'condominio_id' => $condominio->id, 'nome' => 'Pintura do prédio',
        ]);

        $campanha = CobrancaExtraordinaria::factory()->create([
            'condominio_id' => $condominio->id,
            'nome' => 'Taxa para pintura do prédio',
            'finalidade_id' => $finalidade->id,
            'valor_total' => '24000.00',
            'valor_por_unidade' => '50.00',
            // Vigência parcial: cobre só o 2º semestre de 2030
            'vigencia_inicio' => '2030-07-01',
            'vigencia_fim' => '2030-12-31',
        ]);
    }

    return compact('condominio', 'unidade', 'campanha');
}

it('usa 100,00 como taxa ordinária padrão, não 150,00', function () {
    Condominio::factory()->create();

    expect(ConfiguracoesCondominio::taxaMensalidadePadrao())->toBe('100.00');
});

it('lança a taxa composta somando a contribuição só dentro da vigência', function () {
    cenarioLancamento();

    app(LancarTaxas::class)->executar(2030, '100.00');

    $taxas = TaxaCondominial::query()->where('competencia_ano', 2030)->orderBy('competencia_mes')->get();

    expect($taxas)->toHaveCount(12);

    // Jan–jun: fora da vigência → só a ordinária
    foreach ($taxas->take(6) as $taxa) {
        expect((string) $taxa->valor_original)->toBe('100.00')
            ->and($taxa->itens)->toHaveCount(1)
            ->and($taxa->itens[0]->descricao)->toBe('Taxa condominial');
    }

    // Jul–dez: dentro da vigência → 100 + 50
    foreach ($taxas->skip(6) as $taxa) {
        expect((string) $taxa->valor_original)->toBe('150.00')
            ->and($taxa->itens)->toHaveCount(2)
            ->and($taxa->itens[1]->descricao)->toBe('Taxa para pintura do prédio')
            ->and((string) $taxa->itens[1]->valor)->toBe('50.00')
            ->and($taxa->itens[1]->ordem)->toBe(1);
    }
});

it('vincula finalidade e origem nos itens lançados', function () {
    $cenario = cenarioLancamento();

    app(LancarTaxas::class)->executar(2030, '100.00');

    $dezembro = TaxaCondominial::query()->where('competencia_mes', 12)->sole();

    expect($dezembro->itens[0]->finalidade->nome)->toBe('Custeio ordinário')
        ->and($dezembro->itens[1]->finalidade->nome)->toBe('Pintura do prédio')
        ->and($dezembro->itens[1]->origem_id)->toBe($cenario['campanha']->id)
        ->and($dezembro->itens[1]->origem_type)->toBe(CobrancaExtraordinaria::class);
});

it('lança apenas a ordinária quando não há campanha recorrente', function () {
    cenarioLancamento(comCampanha: false);

    app(LancarTaxas::class)->executar(2030, '100.00');

    $taxas = TaxaCondominial::query()->where('competencia_ano', 2030)->get();

    expect($taxas->every(fn ($t): bool => (string) $t->valor_original === '100.00'))->toBeTrue()
        ->and($taxas->every(fn ($t): bool => $t->itens->count() === 1))->toBeTrue();
});

it('ignora campanha ativa sem valor por unidade (rateio manual)', function () {
    $cenario = cenarioLancamento();
    $cenario['campanha']->update(['valor_por_unidade' => null]);

    app(LancarTaxas::class)->executar(2030, '100.00');

    $dezembro = TaxaCondominial::query()->where('competencia_mes', 12)->sole();

    expect((string) $dezembro->valor_original)->toBe('100.00')
        ->and($dezembro->itens)->toHaveCount(1);
});

it('mantém a invariante valor_original = SUM(itens) no lançamento em lote', function () {
    cenarioLancamento();

    app(LancarTaxas::class)->executar(2030, '100.00');

    $this->artisan('taxas:verificar-composicao')->assertSuccessful();
});

it('recusa lançar sem o plano de contas R-001', function () {
    Condominio::factory()->create();
    Unidade::factory()->create();

    expect(fn () => app(LancarTaxas::class)->executar(2030, '100.00'))
        ->toThrow(DomainException::class, 'R-001');
});

it('mostra a prévia da composição por competência', function () {
    cenarioLancamento();

    $acao = app(LancarTaxas::class);

    expect($acao->previaComposicao(2030, 1, '100.00'))->toBe([
        ['descricao' => 'Taxa condominial', 'valor' => '100.00'],
    ])->and($acao->previaComposicao(2030, 8, '100.00'))->toBe([
        ['descricao' => 'Taxa condominial', 'valor' => '100.00'],
        ['descricao' => 'Taxa para pintura do prédio', 'valor' => '50.00'],
    ]);
});

it('altera o valor devido ao editar um item da composição', function () {
    cenarioLancamento();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    app(LancarTaxas::class)->executar(2030, '100.00');

    $taxa = TaxaCondominial::query()->where('competencia_mes', 8)->sole();
    $itemPintura = $taxa->itens[1];

    app(SalvarItemTaxa::class)->executar($taxa, ['valor' => '80.00'], $admin, $itemPintura);

    expect((string) $taxa->fresh()->valor_original)->toBe('180.00');
});

it('recusa remover item de taxa com pagamento aplicado', function () {
    $cenario = cenarioLancamento();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);
    $vinculo = UnidadePessoa::factory()->create([
        'unidade_id' => $cenario['unidade']->id,
        'papel' => PapelVinculo::Proprietario,
        'responsavel_financeiro' => true,
    ]);

    app(LancarTaxas::class)->executar(2030, '100.00');

    $taxa = TaxaCondominial::query()->where('competencia_mes', 8)->sole();

    app(RegistrarPagamento::class)->executar(
        $vinculo->pessoa, $cenario['unidade'], '2030-08-10', 'Mensalidade', 150.00, [$taxa->id],
        FormaPagamento::NaoInformado,
    );

    expect(fn () => app(RemoverItemTaxa::class)->executar($taxa->fresh()->itens[1], $admin))
        ->toThrow(DomainException::class, 'já tem pagamento aplicado');
});

it('aplica e retira a cobrança de um intervalo de competências', function () {
    $cenario = cenarioLancamento();

    // Lança o ano sem a campanha, depois aplica retroativamente
    $cenario['campanha']->update(['valor_por_unidade' => null]);
    app(LancarTaxas::class)->executar(2030, '100.00');
    $cenario['campanha']->update(['valor_por_unidade' => '50.00']);

    $acao = app(AplicarCobrancaEmTaxas::class);

    $resultado = $acao->aplicar($cenario['campanha'], 2030, 7, 2030, 12);

    expect($resultado['aplicadas'])->toBe(6)
        ->and($resultado['ignoradas'])->toBe([])
        ->and((string) TaxaCondominial::query()->where('competencia_mes', 8)->sole()->valor_original)->toBe('150.00')
        ->and((string) TaxaCondominial::query()->where('competencia_mes', 3)->sole()->valor_original)->toBe('100.00');

    // Idempotente: aplicar de novo não duplica
    expect($acao->aplicar($cenario['campanha'], 2030, 7, 2030, 12)['aplicadas'])->toBe(0);

    // E retirar volta ao valor anterior
    expect($acao->retirar($cenario['campanha'], 2030, 7, 2030, 12)['retiradas'])->toBe(6)
        ->and((string) TaxaCondominial::query()->where('competencia_mes', 8)->sole()->valor_original)->toBe('100.00');
});

it('recusa aplicar cobrança sem valor por unidade', function () {
    $cenario = cenarioLancamento();
    $cenario['campanha']->update(['valor_por_unidade' => null]);
    app(LancarTaxas::class)->executar(2030, '100.00');

    expect(fn () => app(AplicarCobrancaEmTaxas::class)->aplicar($cenario['campanha'], 2030, 1, 2030, 12))
        ->toThrow(DomainException::class, 'valor por unidade');
});
