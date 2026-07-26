<div class="card">
    <x-page-header title="Usuários" class="mb-4">
        <x-button variant="secondary" :href="route('usuarios.acessos')">Log de acessos</x-button>
        <x-button wire:click="novo">Novo Usuário</x-button>
    </x-page-header>

    <x-table>
        <x-slot:head>
            <tr>
                <th>Nome</th>
                <th>Email</th>
                <th>Papel</th>
                <th>Último acesso</th>
                <th class="text-right">Ações</th>
            </tr>
        </x-slot:head>
        @forelse ($usuarios as $usuario)
            <tr>
                <td>{{ $usuario->name }}</td>
                <td>{{ $usuario->email }}</td>
                <td>{{ $usuario->papel->rotulo() }}</td>
                <td>
                    {{ $usuario->ultimo_acesso ? \Illuminate\Support\Carbon::parse($usuario->ultimo_acesso)->format('d/m/Y H:i') : '-' }}
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-2">
                        <x-table-action wire:click="editar({{ $usuario->id }})" title="Editar">Editar</x-table-action>
                        @if ($usuario->id !== auth()->id())
                            <x-table-action variant="danger" wire:click="confirmarExclusao({{ $usuario->id }})" title="Excluir">
                                Excluir
                            </x-table-action>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="py-6 text-center text-slate-500">Nenhum usuário cadastrado.</td>
            </tr>
        @endforelse
    </x-table>

    {{-- Modal de formulário (novo/editar) --}}
    @if ($exibirFormulario)
        <x-modal :title="$usuarioId ? 'Editar Usuário' : 'Novo Usuário'" close="cancelar">
            <form wire:submit="salvar" class="space-y-4">
                <x-input label="Nome *" name="name" id="form-name" wire:model="name" />

                <x-input label="Email *" name="email" id="form-email" type="email" wire:model="email" />

                <x-select label="Papel *" name="papel" id="form-papel" wire:model.live="papel">
                    @foreach ($papeis as $opcao)
                        <option value="{{ $opcao->value }}">{{ $opcao->rotulo() }}</option>
                    @endforeach
                </x-select>

                <x-select label="Pessoa vinculada{{ $papel === 'proprietario' ? ' *' : '' }}" name="pessoaId"
                          id="form-pessoa" wire:model="pessoaId"
                          help="Obrigatória para o papel Proprietário (portal do condômino).">
                    <option value="">Nenhuma</option>
                    @foreach ($pessoas as $pessoa)
                        <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                    @endforeach
                </x-select>

                <x-input label="Senha {{ $usuarioId ? '(deixe em branco para manter)' : '*' }}" name="password"
                         id="form-password" type="password" wire:model="password" autocomplete="new-password" />

                <x-input label="Confirme a senha" name="password_confirmation" id="form-password-confirmation"
                         type="password" wire:model="password_confirmation" autocomplete="new-password" />

                <div class="flex justify-end gap-2 pt-2">
                    <x-button variant="secondary" wire:click="cancelar">Cancelar</x-button>
                    <x-button type="submit" wire:loading.attr="disabled">Salvar</x-button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- Modal de confirmação de exclusão --}}
    @if ($confirmandoExclusaoId !== null)
        <x-modal close="cancelarExclusao">
            <p class="text-sm text-slate-700">Tem certeza que deseja excluir este usuário?</p>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelarExclusao">Cancelar</x-button>
                <x-button variant="danger" wire:click="excluir" wire:loading.attr="disabled">Excluir</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
