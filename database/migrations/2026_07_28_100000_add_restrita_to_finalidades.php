<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segregação do saldo por destinação.
 *
 * Uma finalidade `restrita` é dinheiro carimbado: entra no caixa, mas não está
 * disponível para o custeio ordinário (despesas correntes de manutenção e
 * administração). O Resumo financeiro passa a decompor o saldo final em
 * "vinculado a finalidades" + "disponível para custeio".
 *
 * Default `false` — nada muda para as finalidades já cadastradas; a marcação é
 * uma decisão do síndico, feita no CRUD de finalidades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finalidades', function (Blueprint $table) {
            $table->boolean('restrita')->default(false)->after('meta_valor');
        });
    }

    public function down(): void
    {
        Schema::table('finalidades', function (Blueprint $table) {
            $table->dropColumn('restrita');
        });
    }
};
