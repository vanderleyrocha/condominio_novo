<?php

declare(strict_types=1);

use App\Http\Controllers\PdfController;
use App\Livewire\Acesso\Login;
use App\Livewire\Acesso\Usuarios\LogAcessos;
use App\Livewire\Cadastros\Pessoas\Formulario;
use App\Livewire\Financeiro\Dividas\PorImovel;
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
