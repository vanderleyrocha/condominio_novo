<div>
    <h1 class="mb-1 text-center text-xl font-semibold">{{ \App\Support\ParametrosCondominio::nomeCondominio() }}</h1>
    <p class="mb-6 text-center text-sm text-gray-500">Acesso ao sistema</p>

    <form wire:submit="entrar" class="space-y-4">
        <div>
            <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Usuário</label>
            <input id="name" type="text" wire:model="name" autofocus autocomplete="username"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Senha</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" wire:model="remember" class="rounded border-gray-300">
            Lembrar-me
        </label>

        <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-md bg-brand px-4 py-2 font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
            <span wire:loading.remove>Iniciar</span>
            <span wire:loading>Entrando...</span>
        </button>
    </form>
</div>
