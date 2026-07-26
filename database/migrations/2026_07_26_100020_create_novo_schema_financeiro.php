<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 da remodelagem — domínio financeiro do schema novo.
 *
 * `pagamentos_novo`: nome temporário durante a coexistência — a tabela antiga
 * `pagamentos` não pode ser alterada até a Fase 4; no cutover ocorre o rename
 * para `pagamentos` (ver 04-plano-migracao.md).
 *
 * Convenção de sinal: estornos têm valor_total/valor_aplicado NEGATIVOS,
 * como no legado (03-modelo-dados.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominio_id')->constrained('condominios')->restrictOnDelete();
            $table->string('codigo', 20);
            $table->string('descricao');
            $table->string('tipo', 20); // App\Enums\TipoPlanoConta
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['condominio_id', 'codigo'], 'uk_plano_conta_codigo');
        });

        Schema::create('taxas_condominiais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->unsignedTinyInteger('competencia_mes');
            $table->unsignedSmallInteger('competencia_ano');
            $table->date('vencimento');
            $table->decimal('valor_original', 12, 2)->default(0);
            $table->decimal('valor_desconto', 12, 2)->default(0);
            $table->decimal('valor_acrescimo', 12, 2)->default(0);
            // Cache de leitura — fonte de verdade é a soma de pagamento_taxa,
            // recalculada exclusivamente por App\Services\StatusTaxaService
            $table->string('status', 20)->default('aberto'); // App\Enums\StatusTaxa
            $table->boolean('contabilizado')->default(true);
            $table->timestamps();
            $table->softDeletes();
            // Duplicidade legada (N-01) foi saneada — garantia sobe para o banco
            $table->unique(['unidade_id', 'competencia_ano', 'competencia_mes'], 'uk_taxa_unidade_competencia');
            $table->index('vencimento');
            $table->index('status');
        });

        Schema::create('pagamentos_novo', function (Blueprint $table) {
            $table->id();
            // Nullable: o legado possui pagamentos sem imóvel associado
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->restrictOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->date('data_pagamento');
            $table->string('descricao');
            $table->decimal('valor_total', 12, 2); // negativo em estornos
            $table->string('forma_pagamento', 20)->default('nao_informado'); // App\Enums\FormaPagamento
            $table->foreignId('estorno_de_id')->nullable()->constrained('pagamentos_novo')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('data_pagamento');
        });

        Schema::create('pagamento_taxa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagamento_id')->constrained('pagamentos_novo')->cascadeOnDelete();
            $table->foreignId('taxa_condominial_id')->constrained('taxas_condominiais')->cascadeOnDelete();
            $table->decimal('valor_aplicado', 10, 2); // negativo em estornos
            $table->timestamps();
            $table->unique(['pagamento_id', 'taxa_condominial_id'], 'uk_pagamento_taxa');
        });

        Schema::create('cobrancas_extraordinarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominio_id')->constrained('condominios')->restrictOnDelete();
            $table->string('nome');
            $table->decimal('valor_total', 12, 2);
            $table->string('metodo_rateio', 20)->default('manual'); // App\Enums\MetodoRateio
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable(); // null = permanente
            $table->boolean('ativa')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cobranca_extraordinaria_taxa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobranca_extraordinaria_id')->constrained('cobrancas_extraordinarias')->cascadeOnDelete();
            $table->foreignId('taxa_condominial_id')->constrained('taxas_condominiais')->cascadeOnDelete();
            $table->decimal('valor', 10, 2);
            $table->timestamps();
            $table->unique(['cobranca_extraordinaria_id', 'taxa_condominial_id'], 'uk_cobranca_ext_taxa');
        });

        Schema::create('lancamentos_financeiros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominio_id')->constrained('condominios')->restrictOnDelete();
            // NOT NULL: todo lançamento tem classificação contábil; a origem
            // polimórfica é rastreio, não classificação (02-mapeamento §8)
            $table->foreignId('plano_conta_id')->constrained('planos_contas')->restrictOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->restrictOnDelete();
            $table->date('data_competencia');
            $table->date('data_lancamento');
            $table->string('descricao');
            $table->decimal('valor', 12, 2);
            $table->string('natureza', 20); // App\Enums\NaturezaLancamento
            $table->boolean('contabilizado')->default(true);
            $table->nullableMorphs('origem');
            $table->timestamps();
            $table->softDeletes();
            $table->index('data_competencia');
            $table->index('data_lancamento');
        });

        Schema::create('indices_economicos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // App\Enums\TipoIndiceEconomico
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->decimal('indice', 8, 4); // variação % mensal
            $table->timestamps();
            $table->unique(['tipo', 'ano', 'mes'], 'uk_indice_tipo_competencia');
        });

        Schema::create('regras_reajuste', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominio_id')->constrained('condominios')->restrictOnDelete();
            // Tipo do índice (não FK para linha mensal de indices_economicos:
            // a regra reajusta pela série do índice, não por um mês específico)
            $table->string('tipo_indice', 20); // App\Enums\TipoIndiceEconomico
            $table->unsignedInteger('periodicidade_meses');
            $table->date('data_base');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            // Nullable: configuração global quando não escopada a um condomínio
            $table->foreignId('condominio_id')->nullable()->constrained('condominios')->cascadeOnDelete();
            $table->string('chave', 100);
            $table->text('valor');
            $table->string('tipo_dado', 20)->default('string');
            $table->timestamps();
            $table->unique(['condominio_id', 'chave'], 'uk_configuracao_chave');
        });

        // Infra do ETL (Fase 2): mapa old_id -> new_id por entidade.
        // Para pessoas o mapa é N:1 (deduplicação por CPF).
        Schema::create('migration_id_map', function (Blueprint $table) {
            $table->id();
            $table->string('entidade', 50);
            $table->unsignedBigInteger('id_antigo');
            $table->unsignedBigInteger('id_novo');
            $table->timestamps();
            $table->unique(['entidade', 'id_antigo'], 'uk_id_map_entidade_antigo');
            $table->index(['entidade', 'id_novo'], 'ix_id_map_entidade_novo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_id_map');
        Schema::dropIfExists('configuracoes');
        Schema::dropIfExists('regras_reajuste');
        Schema::dropIfExists('indices_economicos');
        Schema::dropIfExists('lancamentos_financeiros');
        Schema::dropIfExists('cobranca_extraordinaria_taxa');
        Schema::dropIfExists('cobrancas_extraordinarias');
        Schema::dropIfExists('pagamento_taxa');
        Schema::dropIfExists('pagamentos_novo');
        Schema::dropIfExists('taxas_condominiais');
        Schema::dropIfExists('planos_contas');
    }
};
