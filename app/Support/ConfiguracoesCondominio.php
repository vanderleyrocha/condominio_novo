<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MetodoCorrecao;
use App\Models\Condominio;
use App\Models\Configuracao;

/**
 * Acesso tipado às configurações do condomínio no modelo novo — substitui
 * ParametrosCondominio no cutover (chaves escopadas por condomínio em
 * `configuracoes`; nome do condomínio vive em `condominios.nome`).
 * Os defaults reproduzem os seeds do legado (paridade — REF-002).
 * Escopo: condomínio único enquanto não houver seleção de condomínio.
 */
final class ConfiguracoesCondominio
{
    /** @var array<string, string>|null cache por request */
    private static ?array $cache = null;

    public static function get(string $chave, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            // Sem condomínio cadastrado (instalação nova, testes): valem os defaults
            $condominioId = Condominio::query()->value('id');

            self::$cache = $condominioId === null ? [] : Configuracao::query()
                ->where('condominio_id', $condominioId)
                ->pluck('valor', 'chave')
                ->all();
        }

        return self::$cache[$chave] ?? $default;
    }

    public static function set(string $chave, string $valor, string $tipoDado = 'string'): void
    {
        Configuracao::query()->updateOrCreate(
            ['condominio_id' => self::condominioId(), 'chave' => $chave],
            ['valor' => $valor, 'tipo_dado' => $tipoDado],
        );
        self::$cache = null;
    }

    public static function limparCache(): void
    {
        self::$cache = null;
    }

    public static function nomeCondominio(): string
    {
        return Condominio::query()->value('nome') ?? 'Condomínio Space';
    }

    public static function setNomeCondominio(string $nome): void
    {
        Condominio::query()->whereKey(self::condominioId())->update(['nome' => $nome]);
    }

    public static function taxaMensalidadePadrao(): string
    {
        return self::get('taxa_mensalidade_padrao', '150.00') ?? '150.00';
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

    private static function condominioId(): int
    {
        return (int) (Condominio::query()->value('id')
            ?? throw new \RuntimeException('Nenhum condomínio cadastrado — rode migrar:condominios.'));
    }
}
