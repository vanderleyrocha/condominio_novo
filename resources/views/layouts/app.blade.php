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
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="hidden w-64 shrink-0 flex-col bg-gray-900 text-gray-100 md:flex">
            <div class="border-b border-gray-800 px-6 py-5">
                <span class="text-lg font-semibold">{{ \App\Support\ParametrosCondominio::nomeCondominio() }}</span>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-4 text-sm">
                <x-nav-link route="painel" label="Painel" />
                <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Financeiro</p>
                <x-nav-link route="mensalidades.index" label="Mensalidades" />
                <x-nav-link route="pagamentos.index" label="Pagamentos" />
                <x-nav-link route="receitas.index" label="Receitas" />
                <x-nav-link route="despesas.index" label="Despesas" />
                <x-nav-link route="dividas.index" label="Dívidas" />
                <x-nav-link route="resumo.index" label="Resumo financeiro" />
                <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Cadastros</p>
                <x-nav-link route="proprietarios.index" label="Proprietários" />
                <x-nav-link route="imoveis.index" label="Imóveis" />
                @can('gerenciar', \App\Models\User::class)
                    <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Administração</p>
                    <x-nav-link route="usuarios.index" label="Usuários" />
                    <x-nav-link route="ipca.index" label="Índices IPCA" />
                    <x-nav-link route="cobrancas-extras.index" label="Cobranças extras" />
                    <x-nav-link route="parametros.edit" label="Parâmetros" />
                @endcan
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Topbar --}}
            <header class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3">
                <div class="text-sm text-gray-500">{{ $header ?? '' }}</div>
                <div class="flex items-center gap-4 text-sm">
                    @auth
                        <a href="{{ route('perfil.edit') }}" class="text-gray-700 hover:text-brand">{{ auth()->user()->name }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-gray-500 hover:text-red-600">Sair</button>
                        </form>
                    @endauth
                </div>
            </header>

            <main class="flex-1 p-6">
                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
