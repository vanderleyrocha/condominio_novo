<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 1 do plano de composição de taxas (docs/migration/05-plano-composicao-taxas.md).
 *
 * A taxa condominial deixa de ser um lançamento único e passa a ser o CONTÊINER
 * (a "fatura mensal") de N itens — permitindo cobrar a taxa ordinária e
 * contribuições adicionais de forma discriminada na mesma competência.
 * Em paralelo, `finalidades` dá destinação (afetação) a toda fonte de receita:
 * itens de taxa, lançamentos financeiros e campanhas extraordinárias.
 *
 * Somente ESTRUTURA — nenhuma linha de dado é tocada aqui (a decomposição das
 * taxas existentes é a Etapa 3, no comando `taxas:decompor-composicao`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finalidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominio_id')->constrained('condominios')->restrictOnDelete();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->decimal('meta_valor', 12, 2)->nullable(); // meta de arrecadação
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable(); // null = permanente
            $table->boolean('ativa')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['condominio_id', 'nome'], 'uk_finalidade_nome');
            $table->index('ativa');
        });

        Schema::create('itens_taxa_condominial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxa_condominial_id')->constrained('taxas_condominiais')->cascadeOnDelete();
            // NOT NULL: todo item tem classificação contábil (mesma regra de
            // lancamentos_financeiros.plano_conta_id — 02-mapeamento §8)
            $table->foreignId('plano_conta_id')->constrained('planos_contas')->restrictOnDelete();
            // Nullable: item sem afetação específica = custeio geral
            $table->foreignId('finalidade_id')->nullable()->constrained('finalidades')->restrictOnDelete();
            $table->string('descricao');
            $table->decimal('valor', 10, 2); // tabela de rateio menor (03-modelo-dados.md)
            // Ordem de quitação em cascata (D-03): o pago quita os itens nesta
            // ordem; 0 = taxa ordinária, contribuições depois
            $table->unsignedSmallInteger('ordem')->default(0);
            // Rastreio da origem (CobrancaExtraordinaria quando gerado por campanha)
            $table->nullableMorphs('origem');
            $table->timestamps();
            $table->softDeletes();
            // Impede duplicar a mesma linha na mesma competência — é também o que
            // torna o ETL da Etapa 3 idempotente por construção
            $table->unique(['taxa_condominial_id', 'descricao'], 'uk_item_taxa_descricao');
            $table->index(['taxa_condominial_id', 'ordem'], 'ix_item_taxa_ordem');
        });

        Schema::table('lancamentos_financeiros', function (Blueprint $table) {
            $table->foreignId('finalidade_id')->nullable()->after('plano_conta_id')
                ->constrained('finalidades')->restrictOnDelete();
        });

        Schema::table('cobrancas_extraordinarias', function (Blueprint $table) {
            $table->foreignId('finalidade_id')->nullable()->after('nome')
                ->constrained('finalidades')->restrictOnDelete();
            // Valor do item gerado por competência dentro da vigência; o
            // valor_total continua sendo o alvo global da campanha
            $table->decimal('valor_por_unidade', 10, 2)->nullable()->after('valor_total');
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas_extraordinarias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalidade_id');
            $table->dropColumn('valor_por_unidade');
        });

        Schema::table('lancamentos_financeiros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalidade_id');
        });

        Schema::dropIfExists('itens_taxa_condominial');
        Schema::dropIfExists('finalidades');
    }
};
