<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Parametro;
use Illuminate\Database\Seeder;

/**
 * Seeds de paridade (data_migration_plan.md §Seeds de paridade / REF-002):
 * os valores reproduzem exatamente as constantes hardcoded do legado.
 */
class ParametrosSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            'taxa_mensalidade_padrao' => '150.00',
            'data_corte_level_one' => '2024-03-10',
            'nome_condominio' => 'Condomínio Space', // REF-003: confirmar grafia oficial
            'subtitulo_recibo' => 'Bloco R-04 – Manoel Julião',
            'assinatura_recibo' => 'Doneska O. Dávila',
            'metodo_correcao' => 'soma_simples',
            'ano_inicial_filtro_pagamentos' => '2014',
            'cidade_recibo' => 'Rio Branco',
            'identificacao_bloco' => 'Bloco R-04',
            'url_sistema' => 'http://r4.condominio.space/',
            'finalidade_reserva' => 'pintura do prédio',
        ];

        foreach ($parametros as $chave => $valor) {
            Parametro::query()->updateOrCreate(['chave' => $chave], ['valor' => $valor]);
        }
    }
}
