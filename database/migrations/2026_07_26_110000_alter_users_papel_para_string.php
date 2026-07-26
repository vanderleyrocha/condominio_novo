<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Converte users.papel de enum MySQL para string(20), permitindo os novos
 * papéis do modelo de acesso (admin, sindico, proprietario — 03-modelo-dados.md).
 * Os valores existentes ('admin', 'level_one') não são alterados aqui: o remap
 * level_one → sindico acontece no cutover (Fase 4), junto com a reescrita das
 * Policies — mudar antes alteraria o comportamento do sistema em produção.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('papel', 20)->default('level_one')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('papel', ['admin', 'level_one'])->default('level_one')->change();
        });
    }
};
