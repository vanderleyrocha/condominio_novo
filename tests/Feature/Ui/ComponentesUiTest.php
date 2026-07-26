<?php

declare(strict_types=1);

// Smoke tests dos componentes Blade anônimos da modernização de UI.
// Não tocam o banco — rodam mesmo sem pdo_sqlite no CLI.

it('renderiza x-input com label, classe do design system e id derivado do wire:model', function () {
    $view = $this->blade('<x-input label="Ano" wire:model="ano" />');

    $view->assertSee('class="input"', false)
        ->assertSee('for="ano"', false)
        ->assertSee('Ano');
});

it('exibe erro de validação automaticamente no x-input', function () {
    $view = $this->withViewErrors(['ano' => 'O campo ano é obrigatório.'])
        ->blade('<x-input label="Ano" name="ano" />');

    $view->assertSee('error-text', false)
        ->assertSee('O campo ano é obrigatório.');
});

it('renderiza x-select com opções no slot', function () {
    $view = $this->blade('<x-select label="Mês" name="mes"><option value="1">Janeiro</option></x-select>');

    $view->assertSee('<select', false)
        ->assertSee('class="input"', false)
        ->assertSee('Janeiro');
});

it('mapeia a prop variant do x-button para as classes .btn-*', function () {
    $this->blade('<x-button variant="danger">Excluir</x-button>')
        ->assertSee('btn btn-danger', false)
        ->assertSee('type="button"', false);

    $this->blade('<x-button type="submit">Salvar</x-button>')
        ->assertSee('btn btn-primary', false)
        ->assertSee('type="submit"', false);
});

it('renderiza x-button como âncora quando href é informado', function () {
    $this->blade('<x-button href="/pessoas">Nova Pessoa</x-button>')
        ->assertSee('<a href="/pessoas"', false)
        ->assertSee('btn btn-primary', false);
});

it('mapeia a prop variant do x-table-action', function () {
    $this->blade('<x-table-action variant="danger">Excluir</x-table-action>')
        ->assertSee('table-action-danger', false);

    $this->blade('<x-table-action href="/x">Editar</x-table-action>')
        ->assertSee('<a href="/x"', false)
        ->assertSee('table-action', false);
});

it('renderiza x-modal com título, largura e fechamento por escape', function () {
    $view = $this->blade('<x-modal title="Confirmação" close="cancelar" maxWidth="3xl">Conteúdo</x-modal>');

    $view->assertSee('modal-overlay', false)
        ->assertSee('modal-panel max-w-3xl', false)
        ->assertSee('wire:keydown.escape.window="cancelar"', false)
        ->assertSee('Confirmação')
        ->assertSee('Conteúdo');
});

it('renderiza x-table com slots head e foot', function () {
    $view = $this->blade(<<<'BLADE'
        <x-table>
            <x-slot:head><tr><th>Coluna</th></tr></x-slot:head>
            <tr><td>Linha</td></tr>
            <x-slot:foot><tr><td>Total</td></tr></x-slot:foot>
        </x-table>
        BLADE);

    $view->assertSee('table-modern', false)
        ->assertSee('<thead>', false)
        ->assertSee('<tfoot>', false)
        ->assertSee('Coluna')
        ->assertSee('Total');
});

it('renderiza x-stat-card com ícone, tom e rodapé', function () {
    $view = $this->blade(<<<'BLADE'
        <x-stat-card label="Receitas" icon="arrow-trending-up" tone="success">
            R$ 100,00
            <x-slot:footer>Nota</x-slot:footer>
        </x-stat-card>
        BLADE);

    $view->assertSee('section-label', false)
        ->assertSee('bg-emerald-50 text-emerald-600', false)
        ->assertSee('R$ 100,00')
        ->assertSee('Nota');
});

it('renderiza x-icon com o svg do nome pedido', function () {
    $this->blade('<x-icon name="bars-3" class="size-6" />')
        ->assertSee('<svg', false)
        ->assertSee('size-6', false)
        ->assertSee('M3.75 6.75h16.5', false);
});

it('renderiza x-page-header com título, subtítulo e ações', function () {
    $view = $this->blade(<<<'BLADE'
        <x-page-header title="Taxas" subtitle="Gestão de taxas">
            <button>Nova</button>
        </x-page-header>
        BLADE);

    $view->assertSee('page-title', false)
        ->assertSee('Taxas')
        ->assertSee('Gestão de taxas')
        ->assertSee('Nova');
});

it('renderiza x-empty-state com mensagem e ação', function () {
    $view = $this->blade('<x-empty-state message="Nada encontrado."><button>Criar</button></x-empty-state>');

    $view->assertSee('Nada encontrado.')
        ->assertSee('Criar');
});
