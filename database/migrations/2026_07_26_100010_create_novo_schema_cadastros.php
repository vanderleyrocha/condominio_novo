<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 da remodelagem (docs/migration/04-plano-migracao.md): schema novo em
 * paralelo — nenhuma tabela antiga é alterada ou removida (exceto users, cuja
 * adição de pessoa_id é sancionada em 03-modelo-dados.md).
 *
 * Enums são colunas string + backed enum PHP (app/Enums), conforme convenção
 * de 03-modelo-dados.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condominios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->char('cnpj', 14)->nullable()->unique();
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->char('uf', 2)->nullable();
            $table->char('cep', 8)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('blocos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominio_id')->constrained('condominios')->restrictOnDelete();
            $table->string('nome');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['condominio_id', 'nome'], 'uk_bloco_condominio_nome');
        });

        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            // CPF (11) ou CNPJ (14), só dígitos; NULL permitido (inquilino legado
            // sem CPF — 02-mapeamento-de-para.md §1); unique aceita múltiplos NULLs
            $table->string('cpf_cnpj', 14)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('tipo', 20)->default('fisica'); // App\Enums\TipoPessoa
            $table->timestamps();
            $table->softDeletes();
            $table->index('nome');
        });

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominio_id')->constrained('condominios')->restrictOnDelete();
            $table->foreignId('bloco_id')->nullable()->constrained('blocos')->restrictOnDelete();
            $table->string('identificacao');
            // Soma das frações do condomínio deve fechar em 1,0 — daí a precisão 6
            $table->decimal('fracao_ideal', 9, 6)->nullable();
            $table->decimal('area', 8, 2)->nullable();
            $table->unsignedTinyInteger('vagas_garagem')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['condominio_id', 'identificacao'], 'uk_unidade_condominio_ident');
        });

        Schema::create('unidade_pessoa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->string('papel', 20); // App\Enums\PapelVinculo
            // Máximo 1 vínculo vigente com responsavel_financeiro=true por unidade —
            // regra garantida em nível de aplicação (03-modelo-dados.md)
            $table->boolean('responsavel_financeiro')->default(false);
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->timestamps();
            $table->index(['unidade_id', 'papel', 'data_fim'], 'ix_vinculo_unidade_papel');
            $table->index('pessoa_id');
        });

        Schema::create('condominio_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominio_id')->constrained('condominios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['condominio_id', 'user_id'], 'uk_condominio_user');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('pessoa_id')->nullable()->after('id')
                ->constrained('pessoas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pessoa_id');
        });
        Schema::dropIfExists('condominio_user');
        Schema::dropIfExists('unidade_pessoa');
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('pessoas');
        Schema::dropIfExists('blocos');
        Schema::dropIfExists('condominios');
    }
};
