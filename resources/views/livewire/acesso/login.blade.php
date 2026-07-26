<div>
    <div class="mb-8 text-center">
        <span class="mx-auto mb-4 flex size-12 items-center justify-center rounded-xl bg-brand text-lg font-bold text-white" aria-hidden="true">
            {{ mb_strtoupper(mb_substr(\App\Support\ParametrosCondominio::nomeCondominio(), 0, 1)) }}
        </span>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ \App\Support\ParametrosCondominio::nomeCondominio() }}</h1>
        <p class="mt-1 text-sm text-slate-500">Acesso ao sistema</p>
    </div>

    <form wire:submit="entrar" class="space-y-5">
        <div>
            <label for="name" class="label">Usuário</label>
            <input id="name" type="text" wire:model="name" autofocus autocomplete="username"
                   class="input">
            @error('name') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Senha</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password"
                   class="input">
            @error('password') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model="remember" class="checkbox">
            Lembrar-me
        </label>

        <button type="submit" wire:loading.attr="disabled"
                class="btn btn-primary w-full py-2.5">
            <span wire:loading.remove>Iniciar</span>
            <span wire:loading>Entrando...</span>
        </button>
    </form>
</div>
