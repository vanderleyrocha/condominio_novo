<div class="card">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Usuários</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('usuarios.acessos') }}"
               class="btn btn-secondary">
                Log de acessos
            </a>
            <button type="button" wire:click="novo"
                    class="btn btn-primary">
                Novo Usuário
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Papel</th>
                    <th>Último acesso</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $usuario)
                    <tr>
                        <td>{{ $usuario->name }}</td>
                        <td>{{ $usuario->email }}</td>
                        <td>{{ $usuario->papel->rotulo() }}</td>
                        <td>
                            {{ $usuario->ultimo_acesso ? \Illuminate\Support\Carbon::parse($usuario->ultimo_acesso)->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="text-right">
                            <button type="button" wire:click="editar({{ $usuario->id }})" title="Editar"
                                    class="table-action">
                                Editar
                            </button>
                            @if ($usuario->id !== auth()->id())
                                <button type="button" wire:click="confirmarExclusao({{ $usuario->id }})" title="Excluir"
                                        class="table-action-danger">
                                    Excluir
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-slate-500">Nenhum usuário cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal de formulário (novo/editar) --}}
    @if ($exibirFormulario)
        <div class="modal-overlay" wire:keydown.escape.window="cancelar">
            <div class="modal-panel">
                <h2 class="mb-4 text-lg font-semibold">{{ $usuarioId ? 'Editar Usuário' : 'Novo Usuário' }}</h2>

                <form wire:submit="salvar" class="space-y-4">
                    <div>
                        <label for="form-name" class="label">Nome *</label>
                        <input id="form-name" type="text" wire:model="name"
                               class="input">
                        @error('name') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-email" class="label">Email *</label>
                        <input id="form-email" type="email" wire:model="email"
                               class="input">
                        @error('email') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-papel" class="label">Papel *</label>
                        <select id="form-papel" wire:model.live="papel"
                                class="input">
                            @foreach ($papeis as $opcao)
                                <option value="{{ $opcao->value }}">{{ $opcao->rotulo() }}</option>
                            @endforeach
                        </select>
                        @error('papel') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-pessoa" class="label">Pessoa vinculada{{ $papel === 'proprietario' ? ' *' : '' }}</label>
                        <select id="form-pessoa" wire:model="pessoaId" class="input">
                            <option value="">Nenhuma</option>
                            @foreach ($pessoas as $pessoa)
                                <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Obrigatória para o papel Proprietário (portal do condômino).</p>
                        @error('pessoaId') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-password" class="label">
                            Senha {{ $usuarioId ? '(deixe em branco para manter)' : '*' }}
                        </label>
                        <input id="form-password" type="password" wire:model="password" autocomplete="new-password"
                               class="input">
                        @error('password') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-password-confirmation" class="label">Confirme a senha</label>
                        <input id="form-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                               class="input">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="cancelar"
                                class="btn btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                                class="btn btn-primary">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal de confirmação de exclusão --}}
    @if ($confirmandoExclusaoId !== null)
        <div class="modal-overlay" wire:keydown.escape.window="cancelarExclusao">
            <div class="modal-panel">
                <p class="mb-6 text-sm text-slate-700">Tem certeza que deseja excluir este usuário?</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelarExclusao"
                            class="btn btn-secondary">
                        Cancelar
                    </button>
                    <button type="button" wire:click="excluir" wire:loading.attr="disabled"
                            class="btn btn-danger">
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
