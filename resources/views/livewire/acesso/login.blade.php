<div>
    <div class="mb-8 text-center">
        <span class="mx-auto mb-4 flex size-12 items-center justify-center rounded-xl bg-brand text-lg font-bold text-white" aria-hidden="true">
            {{ mb_strtoupper(mb_substr(\App\Support\ConfiguracoesCondominio::nomeCondominio(), 0, 1)) }}
        </span>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ \App\Support\ConfiguracoesCondominio::nomeCondominio() }}</h1>
        <p class="mt-1 text-sm text-slate-500">Acesso ao sistema</p>
    </div>

    <form wire:submit="entrar" class="space-y-5">
        <x-input label="Usuário" wire:model="name" autofocus autocomplete="username" />

        <x-input label="Senha" type="password" wire:model="password" autocomplete="current-password" />

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model="remember" class="checkbox">
            Lembrar-me
        </label>

        <x-button type="submit" wire:loading.attr="disabled" class="w-full py-2.5">
            <span wire:loading.remove>Iniciar</span>
            <span wire:loading>Entrando...</span>
        </x-button>
    </form>
</div>
