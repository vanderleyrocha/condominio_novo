<div class="card">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Imóveis</h1>
        @can('create', \App\Models\Imovel::class)
            <button type="button" wire:click="novo"
                    class="btn btn-primary">
                Novo Imóvel
            </button>
        @endcan
    </div>

    <div class="overflow-x-auto">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Imóvel</th>
                    <th>Proprietário</th>
                    <th>Mensalidades</th>
                    @can('create', \App\Models\Imovel::class)
                        <th class="text-right">Ações</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($imoveis as $imovel)
                    <tr>
                        <td>{{ $imovel->nome }}</td>
                        <td>{{ $imovel->proprietario->nome ?? 'Não informado' }}</td>
                        <td>{{ $imovel->mensalidades_count }}</td>
                        @can('update', $imovel)
                            <td class="text-right">
                                <button type="button" wire:click="editar({{ $imovel->id }})" title="Editar"
                                        class="table-action">
                                    Editar
                                </button>
                                <button type="button" wire:click="confirmarExclusao({{ $imovel->id }})" title="Excluir"
                                        class="table-action-danger">
                                    Excluir
                                </button>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 text-center text-slate-500">Nenhum imóvel cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal de formulário (novo/editar) --}}
    @if ($exibirFormulario)
        <div class="modal-overlay" wire:keydown.escape.window="cancelar">
            <div class="modal-panel">
                <h2 class="mb-4 text-lg font-semibold">{{ $imovelId ? 'Editar Imóvel' : 'Novo Imóvel' }}</h2>

                <form wire:submit="salvar" class="space-y-4">
                    <div>
                        <label for="form-nome" class="label">Nome *</label>
                        <input id="form-nome" type="text" wire:model="nome"
                               class="input">
                        @error('nome') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-proprietario" class="label">Proprietário *</label>
                        <select id="form-proprietario" wire:model="proprietario_id"
                                class="input">
                            <option value="">Selecione</option>
                            @foreach ($proprietarios as $proprietario)
                                <option value="{{ $proprietario->id }}">{{ $proprietario->nome }}</option>
                            @endforeach
                        </select>
                        @error('proprietario_id') <p class="error-text">{{ $message }}</p> @enderror
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
                <p class="mb-6 text-sm text-slate-700">Tem certeza que deseja excluir este imóvel?</p>
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
