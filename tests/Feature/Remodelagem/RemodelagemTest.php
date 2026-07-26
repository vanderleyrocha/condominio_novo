<?php

declare(strict_types=1);

// Fase 2 da remodelagem (docs/migration/04-plano-migracao.md): teste de
// reconciliação do ETL antigo → novo, cobrindo dedupe de CPF, inquilino sem
// documento, estorno com sinal negativo e recálculo de status via BCMath.

use App\Enums\StatusTaxa;
use App\Models\CobrancaExtraordinaria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Monta um legado mínimo que exercita todos os caminhos do ETL.
 */
function semearLegado(): void
{
    $t = now()->toDateTimeString();

    DB::table('parametros')->insert(['chave' => 'nome_condominio', 'valor' => 'Condomínio Teste', 'created_at' => $t, 'updated_at' => $t]);

    DB::table('proprietarios')->insert([
        // p1: simples, responsável proprietário
        ['id' => 1, 'nome' => 'Ana', 'cpf' => '11111111111', 'telefone' => '68911111111',
            'nome_inquilino' => null, 'cpf_inquilino' => null, 'telefone_inquilino' => null,
            'responsavel_pagamento' => 'proprietario', 'created_at' => $t, 'updated_at' => $t],
        // p2: inquilino com CPF igual ao de p1 (dedupe N:1), responsável inquilino
        ['id' => 2, 'nome' => 'Bruno', 'cpf' => '22222222222', 'telefone' => '68922222222',
            'nome_inquilino' => 'Ana (inquilina)', 'cpf_inquilino' => '11111111111', 'telefone_inquilino' => '68911111111',
            'responsavel_pagamento' => 'inquilino', 'created_at' => $t, 'updated_at' => $t],
        // p3: inquilino sem CPF
        ['id' => 3, 'nome' => 'Carla', 'cpf' => '33333333333', 'telefone' => '68933333333',
            'nome_inquilino' => 'Daniel', 'cpf_inquilino' => null, 'telefone_inquilino' => null,
            'responsavel_pagamento' => 'proprietario', 'created_at' => $t, 'updated_at' => $t],
    ]);

    DB::table('imoveis')->insert([
        ['id' => 1, 'proprietario_id' => 1, 'nome' => 'Casa 01', 'created_at' => $t, 'updated_at' => $t],
        ['id' => 2, 'proprietario_id' => 2, 'nome' => 'Casa 02', 'created_at' => $t, 'updated_at' => $t],
        ['id' => 3, 'proprietario_id' => 3, 'nome' => 'Casa 03', 'created_at' => $t, 'updated_at' => $t],
    ]);

    DB::table('mensalidades')->insert([
        // m1: paga integral (100), m2: parcial (40 de 100), m3: aberta,
        // m4: paga e integralmente estornada (soma volta a 0)
        ['id' => 1, 'imovel_id' => 1, 'mes' => 1, 'ano' => 2024, 'vencimento' => '2024-01-10',
            'valor' => '100.00', 'desconto' => '0.00', 'acrescimo' => '0.00', 'valor_pago' => '100.00',
            'pago_em' => '2024-01-05', 'contabilizado' => true, 'created_at' => $t, 'updated_at' => $t],
        ['id' => 2, 'imovel_id' => 1, 'mes' => 2, 'ano' => 2024, 'vencimento' => '2024-02-10',
            'valor' => '100.00', 'desconto' => '0.00', 'acrescimo' => '0.00', 'valor_pago' => '40.00',
            'pago_em' => null, 'contabilizado' => true, 'created_at' => $t, 'updated_at' => $t],
        ['id' => 3, 'imovel_id' => 2, 'mes' => 1, 'ano' => 2024, 'vencimento' => '2024-01-10',
            'valor' => '100.00', 'desconto' => '0.00', 'acrescimo' => '0.00', 'valor_pago' => '0.00',
            'pago_em' => null, 'contabilizado' => true, 'created_at' => $t, 'updated_at' => $t],
        ['id' => 4, 'imovel_id' => 3, 'mes' => 1, 'ano' => 2024, 'vencimento' => '2024-01-10',
            'valor' => '100.00', 'desconto' => '0.00', 'acrescimo' => '0.00', 'valor_pago' => '0.00',
            'pago_em' => null, 'contabilizado' => true, 'created_at' => $t, 'updated_at' => $t],
        // m5: paga no legado SEM registro de pagamento (histórico pré-módulo) →
        // o ETL sintetiza um pagamento histórico cobrindo o valor_pago
        ['id' => 5, 'imovel_id' => 2, 'mes' => 2, 'ano' => 2024, 'vencimento' => '2024-02-10',
            'valor' => '60.00', 'desconto' => '0.00', 'acrescimo' => '0.00', 'valor_pago' => '60.00',
            'pago_em' => '2024-02-08', 'contabilizado' => true, 'created_at' => $t, 'updated_at' => $t],
    ]);

    DB::table('pagamentos')->insert([
        ['id' => 1, 'proprietario_id' => 1, 'imovel_id' => 1, 'pagamento_origem_id' => null,
            'data' => '2024-01-05', 'descricao' => 'Pg m1', 'valor' => '100.00', 'estornado' => false, 'created_at' => $t, 'updated_at' => $t],
        ['id' => 2, 'proprietario_id' => 1, 'imovel_id' => 1, 'pagamento_origem_id' => null,
            'data' => '2024-02-05', 'descricao' => 'Pg parcial m2', 'valor' => '40.00', 'estornado' => false, 'created_at' => $t, 'updated_at' => $t],
        ['id' => 3, 'proprietario_id' => 3, 'imovel_id' => 3, 'pagamento_origem_id' => null,
            'data' => '2024-01-08', 'descricao' => 'Pg m4', 'valor' => '100.00', 'estornado' => true, 'created_at' => $t, 'updated_at' => $t],
        ['id' => 4, 'proprietario_id' => 3, 'imovel_id' => 3, 'pagamento_origem_id' => 3,
            'data' => '2024-01-09', 'descricao' => 'Estorno do pagamento #3', 'valor' => '-100.00', 'estornado' => false, 'created_at' => $t, 'updated_at' => $t],
    ]);

    DB::table('pagamento_mensalidades')->insert([
        ['id' => 1, 'pagamento_id' => 1, 'mensalidade_id' => 1, 'valor' => '100.00', 'created_at' => $t, 'updated_at' => $t],
        ['id' => 2, 'pagamento_id' => 2, 'mensalidade_id' => 2, 'valor' => '40.00', 'created_at' => $t, 'updated_at' => $t],
        ['id' => 3, 'pagamento_id' => 3, 'mensalidade_id' => 4, 'valor' => '100.00', 'created_at' => $t, 'updated_at' => $t],
        ['id' => 4, 'pagamento_id' => 4, 'mensalidade_id' => 4, 'valor' => '-100.00', 'created_at' => $t, 'updated_at' => $t],
    ]);

    DB::table('despesa_tipos')->insert(['id' => 1, 'descricao' => 'Energia', 'created_at' => $t, 'updated_at' => $t]);
    DB::table('despesas')->insert(['id' => 1, 'despesa_tipo_id' => 1, 'data' => '2024-01-15',
        'descricao' => 'Conta de luz', 'valor' => '50.00', 'contabilizado' => true, 'created_at' => $t, 'updated_at' => $t]);

    DB::table('cobrancas_extras')->insert(['id' => 1, 'nome' => 'Obra do muro', 'valor' => '500.00',
        'vigencia_inicio' => '2024-01-01', 'vigencia_fim' => null, 'ativa' => true, 'created_at' => $t, 'updated_at' => $t]);
    DB::table('cobranca_extra_mensalidade')->insert(['id' => 1, 'cobranca_extra_id' => 1, 'mensalidade_id' => 3,
        'valor' => '20.00', 'created_at' => $t, 'updated_at' => $t]);

    DB::table('receitas')->insert([
        ['id' => 1, 'data' => '2024-01-20', 'descricao' => 'Rateio obra', 'valor' => '500.00',
            'contabilizado' => true, 'cobranca_extra_id' => 1, 'created_at' => $t, 'updated_at' => $t],
        ['id' => 2, 'data' => '2024-01-22', 'descricao' => 'Receita avulsa', 'valor' => '30.00',
            'contabilizado' => true, 'cobranca_extra_id' => null, 'created_at' => $t, 'updated_at' => $t],
    ]);

    DB::table('ipcas')->insert(['id' => 1, 'ano' => 2024, 'mes' => 1, 'indice' => '0.4200', 'created_at' => $t, 'updated_at' => $t]);

    User::factory()->create();
}

it('migra o legado completo com reconciliação sem divergências', function () {
    semearLegado();

    $this->artisan('migrar:remodelagem')->assertSuccessful();
    // Fase 3: validação profunda (por unidade/competência, cobertura, status, integridade)
    $this->artisan('migrar:validar-remodelagem')->assertSuccessful();

    // Condomínio único nomeado pelo parâmetro do legado
    expect(DB::table('condominios')->count())->toBe(1)
        ->and(DB::table('condominios')->value('nome'))->toBe('Condomínio Teste');

    // Dedupe: Ana (p1) == inquilina de p2 pelo CPF → 4 pessoas, não 5
    expect(DB::table('pessoas')->count())->toBe(4)
        ->and(DB::table('pessoas')->where('cpf_cnpj', '11111111111')->count())->toBe(1)
        ->and(DB::table('pessoas')->whereNull('cpf_cnpj')->count())->toBe(1);

    // Vínculos: casa 1 → 1; casas 2 e 3 → 2 cada (proprietário + inquilino)
    expect(DB::table('unidade_pessoa')->count())->toBe(5);

    // Responsável financeiro da casa 2 é a inquilina (pessoa da Ana, deduplicada)
    $anaId = (int) DB::table('pessoas')->where('cpf_cnpj', '11111111111')->value('id');
    expect(DB::table('unidade_pessoa')->where('unidade_id', 2)->where('papel', 'inquilino')
        ->where('responsavel_financeiro', true)->value('pessoa_id'))->toBe($anaId);

    // Status recalculados via BCMath a partir de pagamento_taxa
    $status = DB::table('taxas_condominiais')->pluck('status', 'id');
    expect($status[1])->toBe(StatusTaxa::Pago->value)
        ->and($status[2])->toBe(StatusTaxa::PagoParcial->value)
        ->and($status[3])->toBe(StatusTaxa::Aberto->value)
        ->and($status[4])->toBe(StatusTaxa::Aberto->value) // 100 - 100 (estorno) = 0
        ->and($status[5])->toBe(StatusTaxa::Pago->value);  // via pagamento histórico sintetizado

    // Estorno: sinal negativo preservado + autorrelação resolvida
    $estorno = DB::table('pagamentos_novo')->where('id', 4)->first();
    expect((float) $estorno->valor_total)->toBe(-100.0)
        ->and((int) $estorno->estorno_de_id)->toBe(3);

    // Pagamento histórico: 4 migrados + 1 sintetizado para m5, rastreado no mapa
    expect(DB::table('pagamentos_novo')->count())->toBe(5);
    $historicoId = (int) DB::table('migration_id_map')
        ->where('entidade', 'pagamento_historico')->where('id_antigo', 5)->value('id_novo');
    $historico = DB::table('pagamentos_novo')->where('id', $historicoId)->first();
    expect((float) $historico->valor_total)->toBe(60.0)
        ->and($historico->data_pagamento)->toBe('2024-02-08')
        ->and(DB::table('pagamento_taxa')->where('pagamento_id', $historicoId)->where('taxa_condominial_id', 5)->exists())->toBeTrue();

    // Cobertura total: Σ pagamento_taxa == Σ mensalidades.valor_pago
    expect((string) DB::table('pagamento_taxa')->sum('valor_aplicado'))
        ->toBe((string) DB::table('mensalidades')->sum('valor_pago'));

    // Lançamentos: 1 despesa + 2 receitas; origem polimórfica na receita da obra
    expect(DB::table('lancamentos_financeiros')->count())->toBe(3);
    $rateio = DB::table('lancamentos_financeiros')->where('descricao', 'Rateio obra')->first();
    expect($rateio->origem_type)->toBe(CobrancaExtraordinaria::class)
        ->and((int) $rateio->origem_id)->toBe(1);

    // Planos: 1 de despesa (Energia) + R-001/R-002
    expect(DB::table('planos_contas')->count())->toBe(3);

    // Somas financeiras batem (reconciliação já validada pelo exit code, mas reforça):
    // pagamentos migrados 1:1 (sem os históricos sintetizados) preservam a soma do legado
    $idsHistoricos = DB::table('migration_id_map')->where('entidade', 'pagamento_historico')->pluck('id_novo');
    expect((string) DB::table('pagamentos_novo')->whereNotIn('id', $idsHistoricos)->sum('valor_total'))
        ->toBe((string) DB::table('pagamentos')->sum('valor'));

    // Índices e usuários
    expect(DB::table('indices_economicos')->where('tipo', 'ipca')->count())->toBe(1)
        ->and(DB::table('condominio_user')->count())->toBe(1);
});

it('é idempotente por reconstrução: re-executar produz o mesmo resultado', function () {
    semearLegado();

    $this->artisan('migrar:remodelagem')->assertSuccessful();
    $antes = [
        'pessoas' => DB::table('pessoas')->count(),
        'vinculos' => DB::table('unidade_pessoa')->count(),
        'taxas' => DB::table('taxas_condominiais')->count(),
        'pagamentos' => DB::table('pagamentos_novo')->count(),
        'lancamentos' => DB::table('lancamentos_financeiros')->count(),
        'mapa' => DB::table('migration_id_map')->count(),
    ];

    $this->artisan('migrar:remodelagem')->assertSuccessful();

    expect([
        'pessoas' => DB::table('pessoas')->count(),
        'vinculos' => DB::table('unidade_pessoa')->count(),
        'taxas' => DB::table('taxas_condominiais')->count(),
        'pagamentos' => DB::table('pagamentos_novo')->count(),
        'lancamentos' => DB::table('lancamentos_financeiros')->count(),
        'mapa' => DB::table('migration_id_map')->count(),
    ])->toBe($antes);
});

it('aborta se houver mensalidades duplicadas na origem (proteção N-01)', function () {
    semearLegado();

    // Duplica a competência da mensalidade 1 no mesmo imóvel
    DB::table('mensalidades')->insert(['id' => 99, 'imovel_id' => 1, 'mes' => 1, 'ano' => 2024,
        'vencimento' => '2024-01-10', 'valor' => '100.00', 'desconto' => '0.00', 'acrescimo' => '0.00',
        'valor_pago' => '0.00', 'pago_em' => null, 'contabilizado' => true,
        'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('migrar:remodelagem')->assertFailed();
});

it('comando isolado exige destino vazio', function () {
    semearLegado();

    $this->artisan('migrar:condominios')->assertSuccessful();
    // Segunda execução sem --truncar deve falhar (destino não vazio)
    $this->artisan('migrar:condominios')->assertFailed();
    // Com --truncar, reconstrói só o próprio destino
    $this->artisan('migrar:condominios --truncar')->assertSuccessful();
    expect(DB::table('condominios')->count())->toBe(1);
});
