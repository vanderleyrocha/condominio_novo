<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MetodoCorrecao;
use App\Models\Parametro;

/**
 * Acesso tipado aos parâmetros de negócio (DA-04).
 * Substitui as constantes hardcoded do legado (BR-DESCARTAR-007);
 * os valores seed reproduzem exatamente o legado (paridade — REF-002).
 */
final class ParametrosCondominio
{
    /** @var array<string, string>|null cache por request */
    private static ?array $cache = null;

    public static function get(string $chave, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = Parametro::query()->pluck('valor', 'chave')->all();
        }

        return self::$cache[$chave] ?? $default;
    }

    public static function set(string $chave, string $valor): void
    {
        Parametro::query()->updateOrCreate(['chave' => $chave], ['valor' => $valor]);
        self::$cache = null;
    }

    public static function limparCache(): void
    {
        self::$cache = null;
    }

    public static function taxaMensalidadePadrao(): string
    {
        return self::get('taxa_mensalidade_padrao', '150.00') ?? '150.00';
    }

    public static function dataCorteLevelOne(): string
    {
        return self::get('data_corte_level_one', '2024-03-10') ?? '2024-03-10';
    }

    public static function nomeCondominio(): string
    {
        return self::get('nome_condominio', 'Condomínio Space') ?? 'Condomínio Space';
    }

    public static function subtituloRecibo(): string
    {
        return self::get('subtitulo_recibo', 'Bloco R-04 – Manoel Julião') ?? '';
    }

    public static function assinaturaRecibo(): string
    {
        return self::get('assinatura_recibo', 'Doneska O. Dávila') ?? '';
    }

    public static function metodoCorrecao(): MetodoCorrecao
    {
        return MetodoCorrecao::from(self::get('metodo_correcao', 'soma_simples') ?? 'soma_simples');
    }

    public static function anoInicialFiltroPagamentos(): int
    {
        return (int) (self::get('ano_inicial_filtro_pagamentos', '2014') ?? '2014');
    }
}
