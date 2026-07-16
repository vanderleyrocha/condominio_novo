<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        {{-- Card Meus dados --}}
        <div class="rounded-lg bg-white p-6 shadow">
            <h1 class="mb-4 text-lg font-semibold">Meus dados</h1>

            <form wire:submit="gravar" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Nome</label>
                    <input type="text" value="{{ $usuario->name }}" disabled
                           class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                    <input id="email" type="email" wire:model="email" placeholder="Email"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="foto" class="mb-1 block text-sm font-medium text-gray-700">Foto de perfil</label>
                    <input id="foto" type="file" wire:model="foto" accept="image/*"
                           class="w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-dark">
                    <div wire:loading wire:target="foto" class="mt-1 text-sm text-gray-500">Enviando...</div>
                    @error('foto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @if ($foto)
                        <img src="{{ $foto->temporaryUrl() }}" alt="Pré-visualização da foto"
                             class="mt-3 h-24 w-24 rounded-full object-cover">
                    @endif
                </div>

                <div class="flex justify-end">
                    <button type="submit" wire:loading.attr="disabled"
                            class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
                        Gravar
                    </button>
                </div>
            </form>
        </div>

        {{-- Card Senha --}}
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-lg font-semibold">Senha</h2>

            <form wire:submit="alterarSenha" class="space-y-4">
                <div>
                    <label for="senha_atual" class="mb-1 block text-sm font-medium text-gray-700">Senha atual</label>
                    <input id="senha_atual" type="password" wire:model="senha_atual" placeholder="Senha atual" autocomplete="current-password"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('senha_atual') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="nova_senha" class="mb-1 block text-sm font-medium text-gray-700">Nova senha</label>
                    <input id="nova_senha" type="password" wire:model="nova_senha" placeholder="Nova senha" autocomplete="new-password"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('nova_senha') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="nova_senha_confirmation" class="mb-1 block text-sm font-medium text-gray-700">Confirme a nova senha</label>
                    <input id="nova_senha_confirmation" type="password" wire:model="nova_senha_confirmation" placeholder="Confirme a nova senha" autocomplete="new-password"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                </div>

                <div class="flex justify-end">
                    <button type="submit" wire:loading.attr="disabled"
                            class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
                        Alterar a senha
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Card do usuário --}}
    <div class="rounded-lg bg-white p-6 text-center shadow">
        @if ($usuario->foto_perfil)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($usuario->foto_perfil) }}" alt="Foto de {{ $usuario->name }}"
                 class="mx-auto mb-4 h-28 w-28 rounded-full object-cover">
        @else
            <div class="mx-auto mb-4 flex h-28 w-28 items-center justify-center rounded-full bg-gray-200 text-3xl font-semibold text-gray-500">
                {{ mb_strtoupper(mb_substr($usuario->name, 0, 1)) }}
            </div>
        @endif
        <p class="text-base font-semibold">{{ $usuario->name }}</p>
        <p class="text-sm text-gray-500">{{ $usuario->email }}</p>
    </div>
</div>
