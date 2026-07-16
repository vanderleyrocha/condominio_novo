<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proprietarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            // CPF armazenado só com dígitos; DV validado na aplicação (Q-03)
            $table->char('cpf', 11)->unique();
            $table->string('telefone', 20);
            $table->string('nome_inquilino')->nullable();
            $table->char('cpf_inquilino', 11)->nullable();
            $table->string('telefone_inquilino', 20)->nullable();
            $table->enum('responsavel_pagamento', ['proprietario', 'inquilino'])->default('proprietario');
            $table->timestamps();
            $table->index('nome');
        });

        Schema::create('imoveis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proprietario_id')->constrained('proprietarios')->restrictOnDelete();
            $table->string('nome')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imoveis');
        Schema::dropIfExists('proprietarios');
    }
};
