<div class="rounded-lg bg-white p-6 shadow">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Usuários</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('usuarios.acessos') }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                Log de acessos
            </a>
            <button type="button" wire:click="novo"
                    class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark">
                Novo Usuário
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-brand">
                    <th class="px-3 py-2 font-semibold">Nome</th>
                    <th class="px-3 py-2 font-semibold">Email</th>
                    <th class="px-3 py-2 font-semibold">Papel</th>
                    <th class="px-3 py-2 font-semibold">Último acesso</th>
                    <th class="px-3 py-2 text-right font-semibold">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($usuarios as $usuario)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2">{{ $usuario->name }}</td>
                        <td class="px-3 py-2">{{ $usuario->email }}</td>
                        <td class="px-3 py-2">{{ $usuario->papel->rotulo() }}</td>
                        <td class="px-3 py-2">
                            {{ $usuario->ultimo_acesso ? \Illuminate\Support\Carbon::parse($usuario->ultimo_acesso)->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" wire:click="editar({{ $usuario->id }})" title="Editar"
                                    class="inline-block rounded-md px-2 py-1 text-brand hover:bg-blue-50">
                                Editar
                            </button>
                            @if ($usuario->id !== auth()->id())
                                <button type="button" wire:click="confirmarExclusao({{ $usuario->id }})" title="Excluir"
                                        class="inline-block rounded-md px-2 py-1 text-red-600 hover:bg-red-50">
                                    Excluir
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">Nenhum usuário cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal de formulário (novo/editar) --}}
    @if ($exibirFormulario)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:keydown.escape.window="cancelar">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow">
                <h2 class="mb-4 text-lg font-semibold">{{ $usuarioId ? 'Editar Usuário' : 'Novo Usuário' }}</h2>

                <form wire:submit="salvar" class="space-y-4">
                    <div>
                        <label for="form-name" class="mb-1 block text-sm font-medium text-gray-700">Nome *</label>
                        <input id="form-name" type="text" wire:model="name"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-email" class="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                        <input id="form-email" type="email" wire:model="email"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-papel" class="mb-1 block text-sm font-medium text-gray-700">Papel *</label>
                        <select id="form-papel" wire:model="papel"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                            @foreach ($papeis as $opcao)
                                <option value="{{ $opcao->value }}">{{ $opcao->rotulo() }}</option>
                            @endforeach
                        </select>
                        @error('papel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-password" class="mb-1 block text-sm font-medium text-gray-700">
                            Senha {{ $usuarioId ? '(deixe em branco para manter)' : '*' }}
                        </label>
                        <input id="form-password" type="password" wire:model="password" autocomplete="new-password"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-password-confirmation" class="mb-1 block text-sm font-medium text-gray-700">Confirme a senha</label>
                        <input id="form-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="cancelar"
                                class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                                class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal de confirmação de exclusão --}}
    @if ($confirmandoExclusaoId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:keydown.escape.window="cancelarExclusao">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow">
                <p class="mb-6 text-sm text-gray-700">Tem certeza que deseja excluir este usuário?</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelarExclusao"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="excluir" wire:loading.attr="disabled"
                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
