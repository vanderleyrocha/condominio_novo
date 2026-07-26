<?php

declare(strict_types=1);

use App\Http\Controllers\PdfController;
use App\Livewire\Acesso\Login;
use App\Livewire\Acesso\Usuarios\LogAcessos;
use App\Livewire\Cadastros\Pessoas\Formulario;
use App\Livewire\Financeiro\Configuracoes\Edicao;
use App\Livewire\Financeiro\Inadimplencia\PorUnidade;
use App\Livewire\Financeiro\Indices\Gestao;
use App\Livewire\Financeiro\Pagamentos\Detalhe;
use App\Livewire\Financeiro\Pagamentos\Estorno;
use App\Livewire\Financeiro\Pagamentos\Registro;
use App\Livewire\Financeiro\PainelInicial;
use App\Livewire\Financeiro\Resumo\Index;
use App\Livewire\Financeiro\Resumo\Intervalo;
use App\Livewire\Financeiro\Taxas\EdicaoIndividual;
use App\Livewire\Financeiro\Taxas\GradeAnual;
use App\Livewire\Financeiro\Taxas\Lancamento;
use App\Livewire\Financeiro\Taxas\Listagem;
use App\Livewire\Financeiro\Taxas\Relatorios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * Rotas nomeadas explícitas (DA-06) — schema novo (remodelagem concluída;
 * o schema antigo foi descomissionado na Fase 5).
 */

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    // Painel inicial = resumo financeiro real (DA-10)
    Route::get('/', PainelInicial::class)->name('painel');

    // Financeiro — Taxas condominiais
    Route::get('/taxas/{ano?}', Listagem::class)
        ->where('ano', '[0-9]{4}')->name('taxas.index');
    Route::get('/taxas/lancamento', Lancamento::class)->name('taxas.lancar');
    Route::get('/taxas/{taxa}/editar', EdicaoIndividual::class)->name('taxas.edit');
    Route::get('/taxas/grade/{ano}', GradeAnual::class)->name('taxas.grade');
    Route::get('/taxas/relatorios', Relatorios::class)->name('taxas.relatorios');

    // Financeiro — Pagamentos
    Route::get('/pagamentos', App\Livewire\Financeiro\Pagamentos\Listagem::class)->name('pagamentos.index');
    Route::get('/pagamentos/registrar', Registro::class)->name('pagamentos.create');
    Route::get('/pagamentos/{pagamento}', Detalhe::class)->name('pagamentos.show');
    Route::get('/pagamentos/{pagamento}/estorno', Estorno::class)->name('pagamentos.estorno');

    // Financeiro — Lançamentos / Inadimplência / Resumo
    Route::get('/lancamentos', App\Livewire\Financeiro\Lancamentos\Listagem::class)->name('lancamentos.index');
    Route::get('/inadimplencia', App\Livewire\Financeiro\Inadimplencia\Listagem::class)->name('inadimplencia.index');
    Route::get('/inadimplencia/unidade/{unidade}', PorUnidade::class)->name('inadimplencia.unidade');
    Route::get('/resumo', Index::class)->name('resumo.index');
    Route::get('/resumo/intervalo', Intervalo::class)->name('resumo.intervalo');

    // Financeiro — Administração
    Route::get('/indices', Gestao::class)->name('indices.index');
    Route::get('/cobrancas-extraordinarias', App\Livewire\Financeiro\CobrancasExtraordinarias\Gestao::class)->name('cobrancas-extraordinarias.index');
    Route::get('/configuracoes', Edicao::class)->name('configuracoes.edit');

    // Cadastros
    Route::get('/pessoas', App\Livewire\Cadastros\Pessoas\Listagem::class)->name('pessoas.index');
    Route::get('/pessoas/nova', Formulario::class)->name('pessoas.create');
    Route::get('/pessoas/{pessoa}/editar', Formulario::class)->name('pessoas.edit');
    Route::get('/unidades', App\Livewire\Cadastros\Unidades\Listagem::class)->name('unidades.index');

    // Acesso
    Route::get('/usuarios', App\Livewire\Acesso\Usuarios\Listagem::class)->name('usuarios.index');
    Route::get('/usuarios/acessos', LogAcessos::class)->name('usuarios.acessos');
    Route::get('/perfil', App\Livewire\Acesso\Perfil\Edicao::class)->name('perfil.edit');

    // PDFs — controllers finos (fluxo não-Livewire)
    Route::get('/pdf/taxas/{taxa}/recibo', [PdfController::class, 'reciboTaxa'])->name('pdf.taxas.recibo');
    Route::get('/pdf/pagamentos/{pagamento}/recibo', [PdfController::class, 'reciboPagamento'])->name('pdf.pagamentos.recibo');
    Route::get('/pdf/inadimplencia/unidade/{unidade}', [PdfController::class, 'inadimplenciaPorUnidade'])->name('pdf.inadimplencia.unidade');
    Route::get('/pdf/inadimplencia/consolidado', [PdfController::class, 'inadimplenciaConsolidada'])->name('pdf.inadimplencia.consolidado');
    Route::get('/pdf/despesas', [PdfController::class, 'despesasPorPeriodo'])->name('pdf.despesas');
    Route::get('/pdf/resumo', [PdfController::class, 'resumoHistorico'])->name('pdf.resumo');
    Route::get('/pdf/resumo/intervalo', [PdfController::class, 'resumoIntervalo'])->name('pdf.resumo.intervalo');
});
