<div class="card">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Pessoas Cadastradas</h1>
        <div class="flex items-center gap-3">
            <input type="text" wire:model.live.debounce.400ms="busca" placeholder="Buscar por nome ou documento..."
                   class="input w-64">
            <a href="{{ route('pessoas.create') }}" class="btn btn-primary">
                Nova Pessoa
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF/CNPJ</th>
                    <th>Telefone</th>
                    <th>E-mail</th>
                    <th class="text-center">Vínculos</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
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
                            <a href="{{ route('pessoas.edit', $pessoa) }}" title="Editar" class="table-action">
                                Editar
                            </a>
                            <button type="button" wire:click="confirmarExclusao({{ $pessoa->id }})" title="Excluir"
                                    class="table-action-danger">
                                Excluir
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-slate-500">Nenhuma pessoa encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $pessoas->links() }}
    </div>

    {{-- Modal de confirmação de exclusão (DEV-13) --}}
    @if ($confirmandoExclusaoId !== null)
        <div class="modal-overlay" wire:keydown.escape.window="cancelarExclusao">
            <div class="modal-panel">
                <p class="mb-6 text-sm text-slate-700">Tem certeza que deseja excluir esta pessoa?</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelarExclusao" class="btn btn-secondary">
                        Cancelar
                    </button>
                    <button type="button" wire:click="excluir" wire:loading.attr="disabled" class="btn btn-danger">
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
