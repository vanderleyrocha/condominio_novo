<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\RegistrarAcesso;
use App\Models\Despesa;
use App\Models\Imovel;
use App\Models\Ipca;
use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Models\Proprietario;
use App\Models\Receita;
use App\Models\User;
use App\Policies\DespesaPolicy;
use App\Policies\ImovelPolicy;
use App\Policies\IpcaPolicy;
use App\Policies\MensalidadePolicy;
use App\Policies\PagamentoPolicy;
use App\Policies\ProprietarioPolicy;
use App\Policies\ReceitaPolicy;
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

        // Toda autorização via Policies (DA-02)
        Gate::policy(Mensalidade::class, MensalidadePolicy::class);
        Gate::policy(Despesa::class, DespesaPolicy::class);
        Gate::policy(Receita::class, ReceitaPolicy::class);
        Gate::policy(Pagamento::class, PagamentoPolicy::class);
        Gate::policy(Proprietario::class, ProprietarioPolicy::class);
        Gate::policy(Imovel::class, ImovelPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Ipca::class, IpcaPolicy::class);
    }
}
