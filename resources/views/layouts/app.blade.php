<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? \App\Support\ParametrosCondominio::nomeCondominio() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <div x-data="{ menuAberto: false }" class="flex min-h-screen">
        {{-- Overlay do menu mobile --}}
        <div x-cloak x-show="menuAberto" x-transition.opacity
             class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm md:hidden"
             @click="menuAberto = false" aria-hidden="true"></div>

        {{-- Sidebar --}}
        <aside x-bind:class="menuAberto ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 -translate-x-full flex-col bg-slate-900 text-slate-100 transition-transform duration-200 ease-out md:static md:translate-x-0"
               aria-label="Navegação principal">
            <div class="flex items-center gap-3 border-b border-slate-800 px-6 py-5">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand text-sm font-bold text-white" aria-hidden="true">
                    {{ mb_strtoupper(mb_substr(\App\Support\ParametrosCondominio::nomeCondominio(), 0, 1)) }}
                </span>
                <span class="truncate text-base font-semibold">{{ \App\Support\ParametrosCondominio::nomeCondominio() }}</span>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
                <x-nav-link route="painel" label="Painel" />
                <p class="px-3 pt-5 pb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Financeiro</p>
                <x-nav-link route="taxas.index" label="Taxas" />
                <x-nav-link route="pagamentos-novo.index" label="Pagamentos" />
                <x-nav-link route="lancamentos.index" label="Lançamentos" />
                <x-nav-link route="inadimplencia.index" label="Inadimplência" />
                <x-nav-link route="resumo-novo.index" label="Resumo financeiro" />
                <p class="px-3 pt-5 pb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Cadastros</p>
                <x-nav-link route="pessoas.index" label="Pessoas" />
                <x-nav-link route="unidades.index" label="Unidades" />
                @can('gerenciar', \App\Models\User::class)
                    <p class="px-3 pt-5 pb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Administração</p>
                    <x-nav-link route="usuarios.index" label="Usuários" />
                    <x-nav-link route="indices.index" label="Índices econômicos" />
                    <x-nav-link route="cobrancas-extraordinarias.index" label="Cobranças extraordinárias" />
                    <x-nav-link route="configuracoes.edit" label="Configurações" />
                @endcan
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Topbar --}}
            <header class="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" @click="menuAberto = true"
                            class="inline-flex size-10 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand md:hidden"
                            aria-label="Abrir menu de navegação">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <div class="truncate text-sm font-medium text-slate-500">{{ $header ?? '' }}</div>
                </div>
                <div class="flex shrink-0 items-center gap-2 text-sm sm:gap-4">
                    @auth
                        <a href="{{ route('perfil.edit') }}"
                           class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 font-medium text-slate-700 transition hover:bg-slate-100 hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                            <span class="flex size-7 items-center justify-center rounded-full bg-brand-light text-xs font-semibold text-brand" aria-hidden="true">
                                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg px-2.5 py-1.5 text-slate-500 transition hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                Sair
                            </button>
                        </form>
                    @endauth
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                @if (session('status'))
                    <div class="alert alert-success mb-6" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mb-6" role="alert">{{ session('error') }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
