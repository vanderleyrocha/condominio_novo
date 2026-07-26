<div class="card">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Proprietários Cadastrados</h1>
        <a href="{{ route('proprietarios.create') }}"
           class="btn btn-primary">
            Novo Proprietário
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Telefone</th>
                    <th>Inquilino</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($proprietarios as $proprietario)
                    <tr>
                        <td>{{ $proprietario->nome }}</td>
                        <td>{{ $this->formatarCpf($proprietario->cpf) }}</td>
                        <td>{{ $proprietario->telefone }}</td>
                        <td>
                            @if ($proprietario->nome_inquilino)
                                {{ $proprietario->nome_inquilino }}
                                @if ($proprietario->telefone_inquilino)
                                    <small class="block text-slate-500">{{ $proprietario->telefone_inquilino }}</small>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('proprietarios.edit', $proprietario) }}" title="Editar"
                               class="table-action">
                                Editar
                            </a>
                            <button type="button" wire:click="confirmarExclusao({{ $proprietario->id }})" title="Excluir"
                                    class="table-action-danger">
                                Excluir
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-slate-500">Nenhum proprietário encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $proprietarios->links() }}
    </div>

    {{-- Modal de confirmação de exclusão (DEV-13) --}}
    @if ($confirmandoExclusaoId !== null)
        <div class="modal-overlay" wire:keydown.escape.window="cancelarExclusao">
            <div class="modal-panel">
                <p class="mb-6 text-sm text-slate-700">Tem certeza que deseja excluir este proprietário?</p>
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
