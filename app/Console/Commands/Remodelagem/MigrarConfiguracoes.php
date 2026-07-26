<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use Illuminate\Support\Facades\DB;

/**
 * Passo 1b — migração SELETIVA de parametros → configuracoes
 * (02-mapeamento-de-para.md, "Tabelas sem alteração estrutural"): apenas as
 * chaves que são de fato configuração do condomínio.
 *
 * NÃO migram:
 * - nome_condominio → virou condominios.nome (migrar:condominios);
 * - data_corte_level_one → regra do modelo antigo, aposentada no cutover.
 */
class MigrarConfiguracoes extends ComandoRemodelagem
{
    /** @var array<string, string> chave => tipo_dado */
    private const CHAVES = [
        'taxa_mensalidade_padrao' => 'decimal',
        'subtitulo_recibo' => 'string',
        'assinatura_recibo' => 'string',
        'metodo_correcao' => 'string',
        'ano_inicial_filtro_pagamentos' => 'int',
        // Chaves auxiliares dos PDFs (migradas apenas se existirem no legado;
        // a aplicação usa os mesmos defaults do ParametrosCondominio)
        'cidade_recibo' => 'string',
        'identificacao_bloco' => 'string',
        'url_sistema' => 'string',
        'assinatura_imagem' => 'string',
        'assinatura_cargo' => 'string',
        'finalidade_reserva' => 'string',
    ];

    protected $signature = 'migrar:configuracoes {--truncar}';

    protected $description = 'Remodelagem: migração seletiva de parâmetros → configurações do condomínio';

    protected function tabelasDestino(): array
    {
        return ['configuracoes'];
    }

    protected function entidadesMapa(): array
    {
        return [];
    }

    protected function executar(): int
    {
        $condominioId = $this->condominioId();

        $parametros = DB::table('parametros')
            ->whereIn('chave', array_keys(self::CHAVES))
            ->get();

        foreach ($parametros as $parametro) {
            DB::table('configuracoes')->insert([
                'condominio_id' => $condominioId,
                'chave' => $parametro->chave,
                'valor' => $parametro->valor,
                'tipo_dado' => self::CHAVES[$parametro->chave],
                'created_at' => $parametro->created_at,
                'updated_at' => $parametro->updated_at,
            ]);
        }

        $ausentes = array_diff(array_keys(self::CHAVES), $parametros->pluck('chave')->all());

        $this->log('Configurações migradas: '.$parametros->count().' de '.count(self::CHAVES).' chaves.');

        if ($ausentes !== []) {
            $this->log('Chaves ausentes no legado (a aplicação usa os defaults): '.implode(', ', $ausentes).'.');
        }

        return self::SUCCESS;
    }
}
