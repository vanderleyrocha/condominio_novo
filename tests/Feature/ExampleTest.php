<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('visitante é redirecionado para o login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('a tela de login responde', function () {
    $this->get(route('login'))->assertOk();
});
