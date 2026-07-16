<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Índices IPCA</h2>
            <button type="button" wire:click="novoIndice"
                    class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Novo índice</button>
        </div>

        @if ($mensagem !== '')
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ $mensagem }}</div>
        @endif

        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-md border border-gray-200 p-4">
                <h3 class="mb-4 text-base font-semibold text-gray-900">
                    {{ $ipcaId === null ? 'Novo índice' : 'Editar índice' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ano</label>
                        <input type="number" wire:model="formAno"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                        @error('formAno') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Mês</label>
                        <select wire:model="formMes"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                            <option value="">Selecione...</option>
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}">{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                            @endforeach
                        </select>
                        @error('formMes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Índice (%)</label>
                        <input type="text" wire:model="formIndice" placeholder="0,0000"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-right focus:border-brand focus:ring-brand">
                        @error('formIndice') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <button type="submit"
                            class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Gravar</button>
                    <button type="button" wire:click="cancelar"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                </div>
            </form>
        @endif

        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2">Ano</th>
                    <th class="px-3 py-2">Mês</th>
                    <th class="px-3 py-2 text-right">Índice (%)</th>
                    <th class="px-3 py-2 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($indices as $ipca)
                    <tr>
                        <td class="px-3 py-2">{{ $ipca->ano }}</td>
                        <td class="px-3 py-2">{{ str_pad((string) $ipca->mes, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $ipca->indice, 4, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" wire:click="editar({{ $ipca->id }})"
                                    class="rounded-md bg-brand px-3 py-1 text-xs font-medium text-white hover:bg-brand-dark">Editar</button>
                            <button type="button" wire:click="excluir({{ $ipca->id }})"
                                    wire:confirm="Excluir o índice {{ str_pad((string) $ipca->mes, 2, '0', STR_PAD_LEFT) }}/{{ $ipca->ano }}?"
                                    class="rounded-md bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-gray-500">Nenhum índice cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $indices->links() }}</div>
    </div>
</div>
