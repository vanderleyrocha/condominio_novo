<div class="card">
    <x-page-header title="Pessoas Cadastradas" class="mb-4">
        <input type="text" wire:model.live.debounce.400ms="busca" placeholder="Buscar por nome ou documento..."
               class="input w-64" aria-label="Buscar pessoa">
        <x-button :href="route('pessoas.create')">Nova Pessoa</x-button>
    </x-page-header>

    <x-table>
        <x-slot:head>
            <tr>
                <th>Nome</th>
                <th>CPF/CNPJ</th>
                <th>Telefone</th>
                <th>E-mail</th>
                <th class="text-center">Vínculos</th>
                <th class="text-right">Ações</th>
            </tr>
        </x-slot:head>
        @forelse ($pessoas as $pessoa)
            <tr wire:key="pessoa-{{ $pessoa->id }}">
                <td>
                    {{ $pessoa->nome }}
                    @if ($pessoa->tipo === \App\Enums\TipoPessoa::Juridica)
                        <span class="ml-1 text-xs text-slate-500">(PJ)</span>
                    @endif
                </td>
                <td>{{ $this->formatarDocumento($pessoa->cpf_cnpj) }}</td>
                <td>{{ $pessoa->telefone ?? '—' }}</td>
                <td>{{ $pessoa->email ?? '—' }}</td>
                <td class="text-center">{{ $pessoa->vinculos_count }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-2">
                        <x-table-action :href="route('pessoas.edit', $pessoa)" title="Editar">Editar</x-table-action>
                        <x-table-action variant="danger" wire:click="confirmarExclusao({{ $pessoa->id }})" title="Excluir">
                            Excluir
                        </x-table-action>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="py-6 text-center text-slate-500">Nenhuma pessoa encontrada.</td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $pessoas->links() }}
    </div>

    {{-- Modal de confirmação de exclusão (DEV-13) --}}
    @if ($confirmandoExclusaoId !== null)
        <x-modal close="cancelarExclusao">
            <p class="text-sm text-slate-700">Tem certeza que deseja excluir esta pessoa?</p>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelarExclusao">Cancelar</x-button>
                <x-button variant="danger" wire:click="excluir" wire:loading.attr="disabled">Excluir</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
