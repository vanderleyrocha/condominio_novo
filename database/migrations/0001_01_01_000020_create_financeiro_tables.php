<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensalidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imovel_id')->constrained('imoveis')->restrictOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('ano');
            $table->date('vencimento');
            $table->decimal('valor', 10, 2)->default(0);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('acrescimo', 10, 2)->default(0);
            $table->decimal('valor_pago', 10, 2)->default(0);
            $table->date('pago_em')->nullable();
            $table->boolean('contabilizado')->default(true);
            $table->timestamps();
            // Não-unique de propósito: o dump legado pode conter duplicatas (N-01);
            // a duplicidade de lançamento é bloqueada em nível de aplicação (BR-MIGRAR-001)
            $table->index(['imovel_id', 'ano', 'mes']);
            $table->index('vencimento');
            $table->index('pago_em');
        });

        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proprietario_id')->constrained('proprietarios')->restrictOnDelete();
            $table->foreignId('imovel_id')->nullable()->constrained('imoveis')->restrictOnDelete();
            $table->foreignId('pagamento_origem_id')->nullable()->constrained('pagamentos')->restrictOnDelete();
            $table->date('data');
            $table->string('descricao');
            $table->decimal('valor', 10, 2); // negativo em estornos
            $table->boolean('estornado')->default(false);
            $table->timestamps();
            $table->index('data');
        });

        Schema::create('pagamento_mensalidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagamento_id')->constrained('pagamentos')->cascadeOnDelete();
            $table->foreignId('mensalidade_id')->constrained('mensalidades')->cascadeOnDelete();
            $table->decimal('valor', 10, 2); // negativo em estornos
            $table->timestamps();
            $table->unique(['pagamento_id', 'mensalidade_id']);
        });

        Schema::create('despesa_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('descricao')->unique();
            $table->timestamps();
        });

        Schema::create('despesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('despesa_tipo_id')->constrained('despesa_tipos')->restrictOnDelete();
            $table->date('data'); // mes/ano do legado são derivados de data
            $table->string('descricao');
            $table->decimal('valor', 10, 2);
            $table->boolean('contabilizado')->default(true);
            $table->timestamps();
            $table->index('data');
        });

        Schema::create('ipcas', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->decimal('indice', 8, 4); // variação % mensal
            $table->timestamps();
            $table->unique(['ano', 'mes']);
        });

        Schema::create('cobrancas_extras', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->decimal('valor', 10, 2);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable(); // null = permanente
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });

        Schema::create('cobranca_extra_mensalidade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobranca_extra_id')->constrained('cobrancas_extras')->cascadeOnDelete();
            $table->foreignId('mensalidade_id')->constrained('mensalidades')->cascadeOnDelete();
            $table->decimal('valor', 10, 2);
            $table->timestamps();
            $table->unique(['cobranca_extra_id', 'mensalidade_id'], 'uk_cobranca_mensalidade');
        });

        Schema::create('receitas', function (Blueprint $table) {
            $table->id();
            $table->date('data');
            $table->string('descricao');
            $table->decimal('valor', 10, 2);
            $table->boolean('contabilizado')->default(true);
            $table->foreignId('cobranca_extra_id')->nullable()->constrained('cobrancas_extras')->nullOnDelete();
            $table->timestamps();
            $table->index('data');
        });

        Schema::create('parametros', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 100)->unique();
            $table->text('valor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
        Schema::dropIfExists('receitas');
        Schema::dropIfExists('cobranca_extra_mensalidade');
        Schema::dropIfExists('cobrancas_extras');
        Schema::dropIfExists('ipcas');
        Schema::dropIfExists('despesas');
        Schema::dropIfExists('despesa_tipos');
        Schema::dropIfExists('pagamento_mensalidades');
        Schema::dropIfExists('pagamentos');
        Schema::dropIfExists('mensalidades');
    }
};
