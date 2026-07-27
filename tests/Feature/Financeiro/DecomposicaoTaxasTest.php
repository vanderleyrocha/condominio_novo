<?php

declare(strict_types=1);

// Etapa 3 de docs/migration/05-plano-composicao-taxas.md — o ETL que decompõe as
// taxas de 150,00 em 100 (custeio) + 50 (pintura). CLAUDE.md exige teste para
// todo script de migração de dados do financeiro.
//
// A propriedade central verificada aqui: a operação é ADITIVA. Nenhum agregado
// financeiro pode mudar — nem para as taxas já pagas.

use App\Actions\Financeiro\RegistrarPagamento;
use App\Console\Commands\Composicao\ComposicaoSnapshot;
use App\Enums\FormaPagamento;
use App\Enums\NaturezaLancamento;
use App\Enums\PapelVinculo;
use App\Enums\StatusTaxa;
use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use App\Models\Finalidade;
use App\Models\ItemTaxa;
use App\Models\LancamentoFinanceiro;
use App\Models\PlanoConta;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Models\UnidadePessoa;
use App\Services\RateioPorFinalidadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Reproduz em miniatura o estado real do banco levantado no plano:
 * taxas compostas de 150,00 (uma paga, uma parcial, uma aberta), uma taxa de
 * 0,00 (competência isenta), a campanha da pintura, a inconsistência N-02
 * (pivô apontando para taxa de 100) e as receitas com finalidade implícita.
 */
function cenarioDecomposicao(): array
{
    $condominio = Condominio::factory()->create();

    $planoTaxa = PlanoConta::factory()->receita()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'R-001', 'descricao' => 'Receita de Taxa Condominial',
    ]);
    PlanoConta::factory()->receita()->create([
        'condominio_id' => $condominio->id, 'codigo' => 'R-002', 'descricao' => 'Cobranças Extraordinárias',
    ]);

    $unidade = Unidade::factory()->create(['condominio_id' => $condominio->id]);
    $vinculo = UnidadePessoa::factory()->create([
        'unidade_id' => $unidade->id, 'papel' => PapelVinculo::Proprietario, 'responsavel_financeiro' => true,
    ]);

    $taxa = fn (int $mes, string $valor, int $ano = 2025): TaxaCondominial => TaxaCondominial::factory()->create([
        'unidade_id' => $unidade->id, 'competencia_mes' => $mes, 'competencia_ano' => $ano,
        'valor_original' => $valor, 'valor_desconto' => '0.00', 'valor_acrescimo' => '0.00',
        'contabilizado' => true,
    ]);

    $paga = $taxa(1, '150.00');
    $parcial = $taxa(2, '150.00');
    $aberta = $taxa(3, '150.00');
    $isenta = $taxa(4, '0.00');
    $legada = $taxa(5, '100.00'); // a inconsistência N-02

    // Pagamentos reais via pagamento_taxa (fonte de verdade do status)
    app(RegistrarPagamento::class)->executar(
        $vinculo->pessoa, $unidade, '2025-01-10', 'Mensalidade 01', 150.00, [$paga->id], FormaPagamento::NaoInformado,
    );
    app(RegistrarPagamento::class)->executar(
        $vinculo->pessoa, $unidade, '2025-02-10', 'Mensalidade 02', 75.00, [$parcial->id], FormaPagamento::NaoInformado,
    );

    $campanha = CobrancaExtraordinaria::factory()->create([
        'condominio_id' => $condominio->id,
        'nome' => 'Poupança pintura do prédio',
        'valor_total' => '50.00',
        'vigencia_inicio' => '2024-04-30',
        'vigencia_fim' => '2026-12-31',
    ]);

    // Pivô legado apontando para a taxa que NÃO tem os 50 embutidos (N-02)
    DB::table('cobranca_extraordinaria_taxa')->insert([
        'cobranca_extraordinaria_id' => $campanha->id,
        'taxa_condominial_id' => $legada->id,
        'valor' => '50.00',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    LancamentoFinanceiro::factory()->create([
        'condominio_id' => $condominio->id, 'plano_conta_id' => $planoTaxa->id,
        'natureza' => NaturezaLancamento::Receita, 'descricao' => 'Rendimentos da conta',
        'valor' => '276.41', 'data_lancamento' => '2025-06-30', 'data_competencia' => '2025-06-30',
    ]);
    LancamentoFinanceiro::factory()->create([
        'condominio_id' => $condominio->id, 'plano_conta_id' => $planoTaxa->id,
        'natureza' => NaturezaLancamento::Receita, 'descricao' => 'Contribuição do Erik para conserto da Bomba',
        'valor' => '30.00', 'data_lancamento' => '2025-07-01', 'data_competencia' => '2025-07-01',
    ]);

    return compact('condominio', 'unidade', 'paga', 'parcial', 'aberta', 'isenta', 'legada', 'campanha');
}

function snapshotAtual(): array
{
    $dados = app(ComposicaoSnapshot::class)->coletar();
    unset($dados['gerado_em']);

    return $dados;
}

it('decompõe as taxas de 150 em 100 + 50 sem alterar nenhum agregado financeiro', function () {
    $cenario = cenarioDecomposicao();
    $antes = snapshotAtual();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    // A garantia central: diferença zero em saldo, pagamentos, status e valores
    expect(snapshotAtual())->toBe($antes);

    foreach (['paga', 'parcial', 'aberta'] as $chave) {
        $taxa = $cenario[$chave]->fresh();

        expect((string) $taxa->valor_original)->toBe('150.00')
            ->and($taxa->itens)->toHaveCount(2)
            ->and($taxa->itens[0]->descricao)->toBe('Taxa condominial')
            ->and((string) $taxa->itens[0]->valor)->toBe('100.00')
            ->and($taxa->itens[0]->ordem)->toBe(0)
            ->and($taxa->itens[1]->descricao)->toBe('Taxa para pintura do prédio')
            ->and((string) $taxa->itens[1]->valor)->toBe('50.00')
            ->and($taxa->itens[1]->ordem)->toBe(1);
    }
});

it('preserva o status das taxas já pagas e parciais', function () {
    $cenario = cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    expect($cenario['paga']->fresh()->status)->toBe(StatusTaxa::Pago)
        ->and($cenario['parcial']->fresh()->status)->toBe(StatusTaxa::PagoParcial)
        ->and($cenario['aberta']->fresh()->status)->toBe(StatusTaxa::Aberto);
});

it('rastreia a origem do item de pintura na campanha e a marca com a finalidade', function () {
    $cenario = cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    $itemPintura = $cenario['paga']->fresh()->itens[1];
    $campanha = $cenario['campanha']->fresh();

    expect($itemPintura->origem_type)->toBe(CobrancaExtraordinaria::class)
        ->and($itemPintura->origem_id)->toBe($campanha->id)
        ->and($itemPintura->finalidade->nome)->toBe('Pintura do prédio')
        ->and($campanha->finalidade->nome)->toBe('Pintura do prédio')
        ->and((string) $campanha->valor_por_unidade)->toBe('50.00');
});

it('faz backfill de item único nas demais taxas, inclusive nas de valor zero', function () {
    $cenario = cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    $isenta = $cenario['isenta']->fresh();
    $legada = $cenario['legada']->fresh();

    expect($isenta->itens)->toHaveCount(1)
        ->and((string) $isenta->itens[0]->valor)->toBe('0.00')
        ->and($legada->itens)->toHaveCount(1)
        ->and((string) $legada->itens[0]->valor)->toBe('100.00')
        ->and($legada->itens[0]->finalidade->nome)->toBe('Custeio ordinário');
});

it('reporta a taxa inconsistente do pivô sem alterá-la', function () {
    $cenario = cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')
        ->expectsOutputToContain('exigindo decisão manual')
        ->assertSuccessful();

    // Não decompõe: mudaria o valor devido de uma taxa existente
    expect((string) $cenario['legada']->fresh()->valor_original)->toBe('100.00')
        ->and($cenario['legada']->fresh()->itens)->toHaveCount(1);
});

it('afeta as receitas com a finalidade correspondente', function () {
    cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    $rendimento = LancamentoFinanceiro::query()->where('descricao', 'Rendimentos da conta')->sole();
    $bomba = LancamentoFinanceiro::query()->where('descricao', 'like', '%Bomba%')->sole();

    expect($rendimento->finalidade->nome)->toBe('Pintura do prédio')
        ->and($bomba->finalidade->nome)->toBe('Conserto da bomba');
});

it('é idempotente: rodar duas vezes não duplica nem altera nada', function () {
    cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    $depoisDaPrimeira = snapshotAtual();
    $itens = ItemTaxa::query()->count();
    $finalidades = Finalidade::query()->count();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    expect(snapshotAtual())->toBe($depoisDaPrimeira)
        ->and(ItemTaxa::query()->count())->toBe($itens)
        ->and(Finalidade::query()->count())->toBe($finalidades);
});

it('reverte para o estado inicial exato', function () {
    $cenario = cenarioDecomposicao();
    $antes = snapshotAtual();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();
    $this->artisan('taxas:decompor-composicao --reverter')->assertSuccessful();

    $campanha = $cenario['campanha']->fresh();

    expect(snapshotAtual())->toBe($antes)
        ->and(ItemTaxa::query()->withTrashed()->count())->toBe(0)
        ->and(Finalidade::query()->withTrashed()->count())->toBe(0)
        ->and($campanha->finalidade_id)->toBeNull()
        ->and($campanha->valor_por_unidade)->toBeNull()
        ->and(LancamentoFinanceiro::query()->whereNotNull('finalidade_id')->count())->toBe(0);
});

it('o dry-run não grava nada', function () {
    cenarioDecomposicao();
    $antes = snapshotAtual();

    $this->artisan('taxas:decompor-composicao --dry-run')
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect(snapshotAtual())->toBe($antes)
        ->and(ItemTaxa::query()->count())->toBe(0)
        ->and(Finalidade::query()->count())->toBe(0);
});

it('deixa a invariante valor_original = SUM(itens) válida em todas as taxas', function () {
    cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();
    $this->artisan('taxas:verificar-composicao')->assertSuccessful();

    $divergentes = TaxaCondominial::query()
        ->whereRaw(
            'valor_original <> COALESCE((SELECT SUM(valor) FROM itens_taxa_condominial
                WHERE taxa_condominial_id = taxas_condominiais.id AND deleted_at IS NULL), 0)'
        )
        ->count();

    expect($divergentes)->toBe(0);
});

it('aborta sem gravar quando falta o plano de contas exigido', function () {
    $condominio = Condominio::factory()->create();
    Unidade::factory()->create(['condominio_id' => $condominio->id]);

    $this->artisan('taxas:decompor-composicao')
        ->expectsOutputToContain('Abortado sem gravar nada')
        ->assertFailed();

    expect(Finalidade::query()->count())->toBe(0);
});

it('rateia o pago em cascata: a pintura só arrecada depois do custeio quitado', function () {
    $cenario = cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    $rateio = app(RateioPorFinalidadeService::class);

    // 150 pagos numa taxa 100+50 → custeio 100, pintura 50
    $paga = $cenario['paga']->fresh()->load('itens');
    expect(array_values($rateio->distribuirTaxa($paga)))->toBe(['100.00', '50.00']);

    // 75 pagos → custeio 75, pintura 0 (D-03)
    $parcial = $cenario['parcial']->fresh()->load('itens');
    expect(array_values($rateio->distribuirTaxa($parcial)))->toBe(['75.00', '0.00']);

    // Nada pago → nada atribuído
    $aberta = $cenario['aberta']->fresh()->load('itens');
    expect(array_values($rateio->distribuirTaxa($aberta)))->toBe(['0.00', '0.00']);
});

it('consolida a arrecadação por finalidade somando taxas e receitas', function () {
    cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    $porFinalidade = app(RateioPorFinalidadeService::class)
        ->arrecadadoPorFinalidade()
        ->mapWithKeys(fn (array $l): array => [$l['finalidade']?->nome ?? 'sem' => $l]);

    // Custeio: cobrado 100×3 + 0 + 100 = 400; arrecadado 100 (paga) + 75 (parcial)
    expect($porFinalidade['Custeio ordinário']['cobrado'])->toBe('400.00')
        ->and($porFinalidade['Custeio ordinário']['arrecadado_taxas'])->toBe('175.00')
        ->and($porFinalidade['Custeio ordinário']['a_receber'])->toBe('225.00');

    // Pintura: cobrado 50×3 = 150; arrecadado só da taxa quitada (50) + rendimento (276,41)
    expect($porFinalidade['Pintura do prédio']['cobrado'])->toBe('150.00')
        ->and($porFinalidade['Pintura do prédio']['arrecadado_taxas'])->toBe('50.00')
        ->and($porFinalidade['Pintura do prédio']['receitas'])->toBe('276.41')
        ->and($porFinalidade['Pintura do prédio']['arrecadado'])->toBe('326.41')
        ->and($porFinalidade['Pintura do prédio']['a_receber'])->toBe('100.00');

    // Bomba: só receita avulsa, nada cobrado em taxa
    expect($porFinalidade['Conserto da bomba']['cobrado'])->toBe('0.00')
        ->and($porFinalidade['Conserto da bomba']['receitas'])->toBe('30.00');
});

// --- Etapa 6: descomissionamento do pivô cobranca_extraordinaria_taxa ---

it('bloqueia o descomissionamento do pivô enquanto houver linha sem item equivalente', function () {
    cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    // A linha do pivô da inconsistência N-02 não tem item equivalente
    $this->artisan('composicao:conferir-pivo')
        ->expectsOutputToContain('sem item equivalente')
        ->assertFailed();

    $this->artisan('composicao:descomissionar-pivo --forcar')
        ->expectsOutputToContain('Abortado')
        ->assertFailed();

    expect(Schema::hasTable('cobranca_extraordinaria_taxa'))->toBeTrue();
});

it('descomissiona o pivô depois de resolvida a exceção', function () {
    $cenario = cenarioDecomposicao();

    $this->artisan('taxas:decompor-composicao')->assertSuccessful();

    // Decisão manual: a linha do pivô era indevida
    DB::table('cobranca_extraordinaria_taxa')
        ->where('taxa_condominial_id', $cenario['legada']->id)
        ->delete();

    $this->artisan('composicao:conferir-pivo')->assertSuccessful();
    $this->artisan('composicao:descomissionar-pivo --forcar')->assertSuccessful();

    expect(Schema::hasTable('cobranca_extraordinaria_taxa'))->toBeFalse();

    // Idempotente
    $this->artisan('composicao:descomissionar-pivo --forcar')
        ->expectsOutputToContain('já foi descomissionado')
        ->assertSuccessful();
});
