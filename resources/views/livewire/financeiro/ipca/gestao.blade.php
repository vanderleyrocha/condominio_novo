<div class="space-y-6">
    <div class="card">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Índices IPCA</h2>
            <button type="button" wire:click="novoIndice"
                    class="btn btn-primary">Novo índice</button>
        </div>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-4 text-base font-semibold text-slate-900">
                    {{ $ipcaId === null ? 'Novo índice' : 'Editar índice' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="label">Ano</label>
                        <input type="number" wire:model="formAno"
                               class="input">
                        @error('formAno') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Mês</label>
                        <select wire:model="formMes"
                                class="input">
                            <option value="">Selecione...</option>
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}">{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                            @endforeach
                        </select>
                        @error('formMes') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Índice (%)</label>
                        <input type="text" wire:model="formIndice" placeholder="0,0000"
                               class="input text-right">
                        @error('formIndice') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <button type="submit"
                            class="btn btn-primary">Gravar</button>
                    <button type="button" wire:click="cancelar"
                            class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        @endif

        <div class="-mx-6 overflow-x-auto px-6">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Ano</th>
                    <th>Mês</th>
                    <th class="text-right">Índice (%)</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($indices as $ipca)
                    <tr>
                        <td>{{ $ipca->ano }}</td>
                        <td>{{ str_pad((string) $ipca->mes, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="text-right">{{ number_format((float) $ipca->indice, 4, ',', '.') }}</td>
                        <td class="text-right">
                            <button type="button" wire:click="editar({{ $ipca->id }})"
                                    class="btn btn-primary btn-sm">Editar</button>
                            <button type="button" wire:click="excluir({{ $ipca->id }})"
                                    wire:confirm="Excluir o índice {{ str_pad((string) $ipca->mes, 2, '0', STR_PAD_LEFT) }}/{{ $ipca->ano }}?"
                                    class="btn btn-danger btn-sm">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 text-center text-slate-500">Nenhum índice cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="mt-4">{{ $indices->links() }}</div>
    </div>
</div>
