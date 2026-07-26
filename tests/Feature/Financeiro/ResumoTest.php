<?php

declare(strict_types=1);

// Testes do redesign do Resumo financeiro (matriz ano × unidades + gráfico).

use App\Actions\Financeiro\RegistrarPagamento;
use App\Enums\PapelUsuario;
use App\Enums\PapelVinculo;
use App\Livewire\Financeiro\Resumo\Index;
use App\Livewire\Financeiro\Resumo\Intervalo;
use App\Models\Condominio;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Models\UnidadePessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function cenarioResumoComPagamento(): Unidade
{
    Condominio::factory()->create();

    $unidade = Unidade::factory()->create(['identificacao' => 'Casa 01']);
    $vinculo = UnidadePessoa::factory()->create([
        'unidade_id' => $unidade->id,
        'papel' => PapelVinculo::Proprietario,
        'responsavel_financeiro' => true,
    ]);

    $taxa = TaxaCondominial::factory()->create([
        'unidade_id' => $unidade->id,
        'competencia_mes' => 1,
        'competencia_ano' => 2030,
        'valor_original' => '100.00',
        'contabilizado' => true,
    ]);

    app(RegistrarPagamento::class)->executar(
        $vinculo->pessoa, $unidade, '2030-01-10', 'Mensalidade', 100.00, [$taxa->id],
    );

    return $unidade;
}

it('renderiza o resumo com matriz, cards e dados do gráfico', function () {
    cenarioResumoComPagamento();

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee('Resumo financeiro')
        ->assertSee('Casa 01')
        ->assertViewHas('totalReceitas', 100.0)
        ->assertViewHas('graficoDados', function (array $dados) {
            return $dados['anos'] === ['2030']
                && $dados['receitas'] === [100.0]
                && $dados['despesas'] === [0.0];
        });
});

it('despacha o evento do gráfico quando o filtro de data muda', function () {
    cenarioResumoComPagamento();

    Livewire::test(Index::class)
        ->set('apartirDe', '2031-01-01')
        ->assertDispatched('resumo-grafico-atualizado');
});

it('renderiza o resumo por intervalo com saldo final', function () {
    cenarioResumoComPagamento();

    Livewire::test(Intervalo::class, ['de' => '2030-01-01', 'ate' => '2030-12-31'])
        ->assertOk()
        ->assertSee('Resumo por intervalo')
        ->assertViewHas('saldoFinal', 100.0);
});

it('abre as rotas do resumo para admin autenticado', function () {
    Condominio::factory()->create();
    $admin = User::factory()->create(['papel' => PapelUsuario::Admin]);

    $this->actingAs($admin)->get(route('resumo.index'))->assertOk();
    $this->actingAs($admin)->get(route('resumo.intervalo'))->assertOk();
});
