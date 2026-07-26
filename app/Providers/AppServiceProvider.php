<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\RegistrarAcesso;
use App\Models\Configuracao;
use App\Models\IndiceEconomico;
use App\Models\LancamentoFinanceiro;
use App\Models\Pagamento;
use App\Models\Pessoa;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Models\User;
use App\Policies\ConfiguracaoPolicy;
use App\Policies\IndiceEconomicoPolicy;
use App\Policies\LancamentoFinanceiroPolicy;
use App\Policies\PagamentoPolicy;
use App\Policies\PessoaPolicy;
use App\Policies\TaxaCondominialPolicy;
use App\Policies\UnidadePolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Auditoria de acesso (INV-03)
        Event::listen(Login::class, RegistrarAcesso::class);

        // Toda autorização via Policies (DA-02) — schema novo (docs/migration)
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Pessoa::class, PessoaPolicy::class);
        Gate::policy(Unidade::class, UnidadePolicy::class);
        Gate::policy(IndiceEconomico::class, IndiceEconomicoPolicy::class);
        Gate::policy(Configuracao::class, ConfiguracaoPolicy::class);
        Gate::policy(TaxaCondominial::class, TaxaCondominialPolicy::class);
        Gate::policy(Pagamento::class, PagamentoPolicy::class);
        Gate::policy(LancamentoFinanceiro::class, LancamentoFinanceiroPolicy::class);
    }
}
