<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * Rotas nomeadas explícitas (DA-06): sem catch-all, sem endpoint JSON auxiliar,
 * sem duplicações (BR-DESCARTAR-004/005/006).
 */

Route::middleware('guest')->group(function () {
    Route::get('/login', App\Livewire\Acesso\Login::class)->name('login');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    // Painel inicial = resumo financeiro real (DA-10)
    Route::get('/', App\Livewire\Financeiro\PainelInicial::class)->name('painel');

    // Financeiro — Mensalidades
    Route::get('/mensalidades/{ano?}', App\Livewire\Financeiro\Mensalidades\Listagem::class)
        ->where('ano', '[0-9]{4}')->name('mensalidades.index');
    Route::get('/mensalidades/lancamento', App\Livewire\Financeiro\Mensalidades\Lancamento::class)->name('mensalidades.lancar');
    Route::get('/mensalidades/{mensalidade}/editar', App\Livewire\Financeiro\Mensalidades\EdicaoIndividual::class)->name('mensalidades.edit');
    Route::get('/mensalidades/grade/{ano}', App\Livewire\Financeiro\Mensalidades\GradeAnual::class)->name('mensalidades.grade');
    Route::get('/mensalidades/relatorios', App\Livewire\Financeiro\Mensalidades\Relatorios::class)->name('mensalidades.relatorios');

    // Financeiro — Pagamentos
    Route::get('/pagamentos', App\Livewire\Financeiro\Pagamentos\Listagem::class)->name('pagamentos.index');
    Route::get('/pagamentos/registrar', App\Livewire\Financeiro\Pagamentos\Registro::class)->name('pagamentos.create');
    Route::get('/pagamentos/{pagamento}', App\Livewire\Financeiro\Pagamentos\Detalhe::class)->name('pagamentos.show');
    Route::get('/pagamentos/{pagamento}/estorno', App\Livewire\Financeiro\Pagamentos\Estorno::class)->name('pagamentos.estorno');

    // Financeiro — Receitas / Despesas
    Route::get('/receitas', App\Livewire\Financeiro\Receitas\Listagem::class)->name('receitas.index');
    Route::get('/despesas', App\Livewire\Financeiro\Despesas\Listagem::class)->name('despesas.index');

    // Financeiro — Dívidas / Resumo
    Route::get('/dividas', App\Livewire\Financeiro\Dividas\Listagem::class)->name('dividas.index');
    Route::get('/dividas/imovel/{imovel}', App\Livewire\Financeiro\Dividas\PorImovel::class)->name('dividas.imovel');
    Route::get('/resumo', App\Livewire\Financeiro\Resumo\Index::class)->name('resumo.index');
    Route::get('/resumo/intervalo', App\Livewire\Financeiro\Resumo\Intervalo::class)->name('resumo.intervalo');

    // Financeiro — Administração
    Route::get('/ipca', App\Livewire\Financeiro\Ipca\Gestao::class)->name('ipca.index');
    Route::get('/cobrancas-extras', App\Livewire\Financeiro\CobrancasExtras\Gestao::class)->name('cobrancas-extras.index');
    Route::get('/parametros', App\Livewire\Financeiro\Parametros\Edicao::class)->name('parametros.edit');

    // Cadastros
    Route::get('/proprietarios', App\Livewire\Cadastros\Proprietarios\Listagem::class)->name('proprietarios.index');
    Route::get('/proprietarios/novo', App\Livewire\Cadastros\Proprietarios\Formulario::class)->name('proprietarios.create');
    Route::get('/proprietarios/{proprietario}/editar', App\Livewire\Cadastros\Proprietarios\Formulario::class)->name('proprietarios.edit');
    Route::get('/imoveis', App\Livewire\Cadastros\Imoveis\Listagem::class)->name('imoveis.index');

    // Acesso
    Route::get('/usuarios', App\Livewire\Acesso\Usuarios\Listagem::class)->name('usuarios.index');
    Route::get('/usuarios/acessos', App\Livewire\Acesso\Usuarios\LogAcessos::class)->name('usuarios.acessos');
    Route::get('/perfil', App\Livewire\Acesso\Perfil\Edicao::class)->name('perfil.edit');

    // PDFs — controllers finos (fluxo não-Livewire)
    Route::get('/pdf/mensalidades/{mensalidade}/recibo', [App\Http\Controllers\PdfController::class, 'reciboMensalidade'])->name('pdf.mensalidades.recibo');
    Route::get('/pdf/pagamentos/{pagamento}/recibo', [App\Http\Controllers\PdfController::class, 'reciboPagamento'])->name('pdf.pagamentos.recibo');
    Route::get('/pdf/dividas/imovel/{imovel}', [App\Http\Controllers\PdfController::class, 'dividasPorImovel'])->name('pdf.dividas.imovel');
    Route::get('/pdf/dividas/consolidado', [App\Http\Controllers\PdfController::class, 'dividasConsolidado'])->name('pdf.dividas.consolidado');
    Route::get('/pdf/despesas', [App\Http\Controllers\PdfController::class, 'despesasPorPeriodo'])->name('pdf.despesas');
    Route::get('/pdf/resumo', [App\Http\Controllers\PdfController::class, 'resumoHistorico'])->name('pdf.resumo');
    Route::get('/pdf/resumo/intervalo', [App\Http\Controllers\PdfController::class, 'resumoIntervalo'])->name('pdf.resumo.intervalo');
});
