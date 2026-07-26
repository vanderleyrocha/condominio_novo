<?php

declare(strict_types=1);

use App\Http\Controllers\PdfController;
use App\Http\Controllers\PdfNovoController;
use App\Livewire\Acesso\Login;
use App\Livewire\Acesso\Usuarios\LogAcessos;
use App\Livewire\Cadastros\Pessoas\Formulario;
use App\Livewire\Financeiro\Dividas\PorImovel;
use App\Livewire\Financeiro\Inadimplencia\PorUnidade;
use App\Livewire\Financeiro\Ipca\Gestao;
use App\Livewire\Financeiro\Mensalidades\EdicaoIndividual;
use App\Livewire\Financeiro\Mensalidades\GradeAnual;
use App\Livewire\Financeiro\Mensalidades\Lancamento;
use App\Livewire\Financeiro\Mensalidades\Listagem;
use App\Livewire\Financeiro\Mensalidades\Relatorios;
use App\Livewire\Financeiro\Pagamentos\Detalhe;
use App\Livewire\Financeiro\Pagamentos\Estorno;
use App\Livewire\Financeiro\Pagamentos\Registro;
use App\Livewire\Financeiro\PainelInicial;
use App\Livewire\Financeiro\PainelNovo;
use App\Livewire\Financeiro\Parametros\Edicao;
use App\Livewire\Financeiro\Resumo\Index;
use App\Livewire\Financeiro\Resumo\Intervalo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * Rotas nomeadas explícitas (DA-06): sem catch-all, sem endpoint JSON auxiliar,
 * sem duplicações (BR-DESCARTAR-004/005/006).
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

    // ===== Modelo novo (Fase 4 da remodelagem) =====

    // Financeiro — Taxas condominiais
    Route::get('/taxas/{ano?}', App\Livewire\Financeiro\Taxas\Listagem::class)
        ->where('ano', '[0-9]{4}')->name('taxas.index');
    Route::get('/taxas/lancamento', App\Livewire\Financeiro\Taxas\Lancamento::class)->name('taxas.lancar');
    Route::get('/taxas/{taxa}/editar', App\Livewire\Financeiro\Taxas\EdicaoIndividual::class)->name('taxas.edit');
    Route::get('/taxas/grade/{ano}', App\Livewire\Financeiro\Taxas\GradeAnual::class)->name('taxas.grade');
    Route::get('/taxas/relatorios', App\Livewire\Financeiro\Taxas\Relatorios::class)->name('taxas.relatorios');

    // Financeiro — Pagamentos (rotas transitórias; renomeadas na Fase 5)
    Route::get('/pagamentos-novo', App\Livewire\Financeiro\PagamentosNovo\Listagem::class)->name('pagamentos-novo.index');
    Route::get('/pagamentos-novo/registrar', App\Livewire\Financeiro\PagamentosNovo\Registro::class)->name('pagamentos-novo.create');
    Route::get('/pagamentos-novo/{pagamento}', App\Livewire\Financeiro\PagamentosNovo\Detalhe::class)->name('pagamentos-novo.show');
    Route::get('/pagamentos-novo/{pagamento}/estorno', App\Livewire\Financeiro\PagamentosNovo\Estorno::class)->name('pagamentos-novo.estorno');

    // Financeiro — Lançamentos / Inadimplência / Resumo / Painel
    Route::get('/lancamentos', App\Livewire\Financeiro\Lancamentos\Listagem::class)->name('lancamentos.index');
    Route::get('/inadimplencia', App\Livewire\Financeiro\Inadimplencia\Listagem::class)->name('inadimplencia.index');
    Route::get('/inadimplencia/unidade/{unidade}', PorUnidade::class)->name('inadimplencia.unidade');
    Route::get('/resumo-novo', App\Livewire\Financeiro\ResumoNovo\Index::class)->name('resumo-novo.index');
    Route::get('/resumo-novo/intervalo', App\Livewire\Financeiro\ResumoNovo\Intervalo::class)->name('resumo-novo.intervalo');
    Route::get('/painel-novo', PainelNovo::class)->name('painel-novo');
    Route::get('/cobrancas-extraordinarias', App\Livewire\Financeiro\CobrancasExtraordinarias\Gestao::class)->name('cobrancas-extraordinarias.index');

    // PDFs — modelo novo
    Route::get('/pdf-novo/taxas/{taxa}/recibo', [PdfNovoController::class, 'reciboTaxa'])->name('pdf-novo.taxas.recibo');
    Route::get('/pdf-novo/pagamentos/{pagamento}/recibo', [PdfNovoController::class, 'reciboPagamento'])->name('pdf-novo.pagamentos.recibo');
    Route::get('/pdf-novo/inadimplencia/unidade/{unidade}', [PdfNovoController::class, 'inadimplenciaPorUnidade'])->name('pdf-novo.inadimplencia.unidade');
    Route::get('/pdf-novo/inadimplencia/consolidado', [PdfNovoController::class, 'inadimplenciaConsolidada'])->name('pdf-novo.inadimplencia.consolidado');
    Route::get('/pdf-novo/despesas', [PdfNovoController::class, 'despesasPorPeriodo'])->name('pdf-novo.despesas');
    Route::get('/pdf-novo/resumo', [PdfNovoController::class, 'resumoHistorico'])->name('pdf-novo.resumo');
    Route::get('/pdf-novo/resumo/intervalo', [PdfNovoController::class, 'resumoIntervalo'])->name('pdf-novo.resumo.intervalo');

    // ===== Schema antigo (fora do menu; removido na Fase 5) =====

    // Financeiro — Mensalidades
    Route::get('/mensalidades/{ano?}', Listagem::class)
        ->where('ano', '[0-9]{4}')->name('mensalidades.index');
    Route::get('/mensalidades/lancamento', Lancamento::class)->name('mensalidades.lancar');
    Route::get('/mensalidades/{mensalidade}/editar', EdicaoIndividual::class)->name('mensalidades.edit');
    Route::get('/mensalidades/grade/{ano}', GradeAnual::class)->name('mensalidades.grade');
    Route::get('/mensalidades/relatorios', Relatorios::class)->name('mensalidades.relatorios');

    // Financeiro — Pagamentos
    Route::get('/pagamentos', App\Livewire\Financeiro\Pagamentos\Listagem::class)->name('pagamentos.index');
    Route::get('/pagamentos/registrar', Registro::class)->name('pagamentos.create');
    Route::get('/pagamentos/{pagamento}', Detalhe::class)->name('pagamentos.show');
    Route::get('/pagamentos/{pagamento}/estorno', Estorno::class)->name('pagamentos.estorno');

    // Financeiro — Receitas / Despesas
    Route::get('/receitas', App\Livewire\Financeiro\Receitas\Listagem::class)->name('receitas.index');
    Route::get('/despesas', App\Livewire\Financeiro\Despesas\Listagem::class)->name('despesas.index');

    // Financeiro — Dívidas / Resumo
    Route::get('/dividas', App\Livewire\Financeiro\Dividas\Listagem::class)->name('dividas.index');
    Route::get('/dividas/imovel/{imovel}', PorImovel::class)->name('dividas.imovel');
    Route::get('/resumo', Index::class)->name('resumo.index');
    Route::get('/resumo/intervalo', Intervalo::class)->name('resumo.intervalo');

    // Financeiro — Administração (modelo novo, Fase 4)
    Route::get('/indices', App\Livewire\Financeiro\Indices\Gestao::class)->name('indices.index');
    Route::get('/configuracoes', App\Livewire\Financeiro\Configuracoes\Edicao::class)->name('configuracoes.edit');

    // Financeiro — Administração (schema antigo; fora do menu, removidas na Fase 5)
    Route::get('/ipca', Gestao::class)->name('ipca.index');
    Route::get('/cobrancas-extras', App\Livewire\Financeiro\CobrancasExtras\Gestao::class)->name('cobrancas-extras.index');
    Route::get('/parametros', Edicao::class)->name('parametros.edit');

    // Cadastros — modelo novo (Fase 4 da remodelagem)
    Route::get('/pessoas', App\Livewire\Cadastros\Pessoas\Listagem::class)->name('pessoas.index');
    Route::get('/pessoas/nova', Formulario::class)->name('pessoas.create');
    Route::get('/pessoas/{pessoa}/editar', Formulario::class)->name('pessoas.edit');
    Route::get('/unidades', App\Livewire\Cadastros\Unidades\Listagem::class)->name('unidades.index');

    // Cadastros — telas do schema antigo (fora do menu; removidas na Fase 5)
    Route::get('/proprietarios', App\Livewire\Cadastros\Proprietarios\Listagem::class)->name('proprietarios.index');
    Route::get('/proprietarios/novo', App\Livewire\Cadastros\Proprietarios\Formulario::class)->name('proprietarios.create');
    Route::get('/proprietarios/{proprietario}/editar', App\Livewire\Cadastros\Proprietarios\Formulario::class)->name('proprietarios.edit');
    Route::get('/imoveis', App\Livewire\Cadastros\Imoveis\Listagem::class)->name('imoveis.index');

    // Acesso
    Route::get('/usuarios', App\Livewire\Acesso\Usuarios\Listagem::class)->name('usuarios.index');
    Route::get('/usuarios/acessos', LogAcessos::class)->name('usuarios.acessos');
    Route::get('/perfil', App\Livewire\Acesso\Perfil\Edicao::class)->name('perfil.edit');

    // PDFs — controllers finos (fluxo não-Livewire)
    Route::get('/pdf/mensalidades/{mensalidade}/recibo', [PdfController::class, 'reciboMensalidade'])->name('pdf.mensalidades.recibo');
    Route::get('/pdf/pagamentos/{pagamento}/recibo', [PdfController::class, 'reciboPagamento'])->name('pdf.pagamentos.recibo');
    Route::get('/pdf/dividas/imovel/{imovel}', [PdfController::class, 'dividasPorImovel'])->name('pdf.dividas.imovel');
    Route::get('/pdf/dividas/consolidado', [PdfController::class, 'dividasConsolidado'])->name('pdf.dividas.consolidado');
    Route::get('/pdf/despesas', [PdfController::class, 'despesasPorPeriodo'])->name('pdf.despesas');
    Route::get('/pdf/resumo', [PdfController::class, 'resumoHistorico'])->name('pdf.resumo');
    Route::get('/pdf/resumo/intervalo', [PdfController::class, 'resumoIntervalo'])->name('pdf.resumo.intervalo');
});
