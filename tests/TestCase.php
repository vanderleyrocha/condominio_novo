<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guarda contra o incidente de 2026-07-26: com `php artisan config:cache`
     * ativo, o Laravel ignora as variáveis do phpunit.xml (sqlite :memory:) e o
     * RefreshDatabase roda migrate:fresh no MySQL real, apagando o banco.
     *
     * A verificação fica em setUpTraits() porque é o ponto imediatamente
     * anterior à execução do RefreshDatabase — em setUp() já seria tarde.
     */
    protected function setUpTraits()
    {
        $conexao = config('database.default');
        $banco = config("database.connections.{$conexao}.database");

        if ($conexao !== 'sqlite' || $banco !== ':memory:') {
            self::fail(
                "ABORTADO antes de tocar o banco: a suíte está apontando para `{$conexao}` ({$banco}) ".
                'em vez de sqlite :memory:. Causa provável: cache de config ativo (criado por '.
                '`php artisan optimize`/`config:cache`) — rode `php artisan optimize:clear` antes dos testes.'
            );
        }

        return parent::setUpTraits();
    }
}
