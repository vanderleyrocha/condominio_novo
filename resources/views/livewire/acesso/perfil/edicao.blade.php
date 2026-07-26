<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        {{-- Card Meus dados --}}
        <div class="card">
            <h1 class="page-title mb-4">Meus dados</h1>

            <form wire:submit="gravar" class="space-y-4">
                <x-input label="Nome" :value="$usuario->name" disabled class="bg-slate-50 text-slate-500" />

                <x-input label="Email" type="email" wire:model="email" placeholder="Email" />

                <div>
                    <label for="foto" class="label">Foto de perfil</label>
                    <input id="foto" type="file" wire:model="foto" accept="image/*"
                           class="w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-dark">
                    <div wire:loading wire:target="foto" class="mt-1 text-sm text-slate-500">Enviando...</div>
                    @error('foto') <p class="error-text">{{ $message }}</p> @enderror
                    @if ($foto)
                        <img src="{{ $foto->temporaryUrl() }}" alt="Pré-visualização da foto"
                             class="mt-3 h-24 w-24 rounded-full object-cover">
                    @endif
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" wire:loading.attr="disabled">Gravar</x-button>
                </div>
            </form>
        </div>

        {{-- Card Senha --}}
        <div class="card">
            <h2 class="mb-4 text-lg font-semibold">Senha</h2>

            <form wire:submit="alterarSenha" class="space-y-4">
                <x-input label="Senha atual" type="password" wire:model="senha_atual"
                         placeholder="Senha atual" autocomplete="current-password" />

                <x-input label="Nova senha" type="password" wire:model="nova_senha"
                         placeholder="Nova senha" autocomplete="new-password" />

                <x-input label="Confirme a nova senha" type="password" wire:model="nova_senha_confirmation"
                         placeholder="Confirme a nova senha" autocomplete="new-password" />

                <div class="flex justify-end">
                    <x-button type="submit" wire:loading.attr="disabled">Alterar a senha</x-button>
                </div>
            </form>
        </div>
    </div>

    {{-- Card do usuário --}}
    <div class="card h-fit text-center">
        @if ($usuario->foto_perfil)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($usuario->foto_perfil) }}" alt="Foto de {{ $usuario->name }}"
                 class="mx-auto mb-4 h-28 w-28 rounded-full object-cover">
        @else
            <div class="mx-auto mb-4 flex h-28 w-28 items-center justify-center rounded-full bg-slate-200 text-3xl font-semibold text-slate-500">
                {{ mb_strtoupper(mb_substr($usuario->name, 0, 1)) }}
            </div>
        @endif
        <p class="text-base font-semibold">{{ $usuario->name }}</p>
        <p class="text-sm text-slate-500">{{ $usuario->email }}</p>
    </div>
</div>
