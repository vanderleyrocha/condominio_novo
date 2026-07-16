<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Ano</label>
                <input type="number" wire:model.live="ano"
                       class="mt-1 w-28 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Mês</label>
                <select wire:model.live="mes"
                        class="mt-1 w-36 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                    <option value="">Todos</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-56 flex-1">
                <label class="block text-sm font-medium text-gray-700">Descrição</label>
                <input type="text" wire:model.live.debounce.400ms="descricao" placeholder="Buscar por descrição..."
                       class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
            </div>
            <button type="button" wire:click="$refresh"
                    class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Filtrar</button>
            @can('create', App\Models\Receita::class)
                <button type="button" wire:click="novaReceita"
                        class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Nova receita</button>
            @endcan
        </div>
    </div>

    @if ($mensagem !== '')
        <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ $mensagem }}</div>
    @endif

    @if ($formAberto)
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="mb-4 text-base font-semibold text-gray-900">
                {{ $receitaId === null ? 'Nova receita' : 'Editar receita' }}
            </h3>
            <form wire:submit="salvar" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data</label>
                        <input type="date" wire:model="formData"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                        @error('formData') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Descrição</label>
                        <input type="text" wire:model="formDescricao"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                        @error('formDescricao') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valor</label>
                        <input type="text" wire:model="formValor" placeholder="0,00"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-right focus:border-brand focus:ring-brand">
                        @error('formValor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Cobrança extra (opcional)</label>
                        <select wire:model="formCobrancaExtraId"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                            <option value="">Nenhuma</option>
                            @foreach ($cobrancasExtras as $cobranca)
                                <option value="{{ $cobranca->id }}">{{ $cobranca->nome }}</option>
                            @endforeach
                        </select>
                        @error('formCobrancaExtraId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @can('gerenciarContabilizado', App\Models\Receita::class)
                        <div class="flex items-end pb-2">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="formContabilizado"
                                       class="rounded border-gray-300 text-brand focus:ring-brand">
                                Contabilizada
                            </label>
                        </div>
                    @endcan
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                            class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Gravar</button>
                    <button type="button" wire:click="cancelar"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Descrição</th>
                    <th class="px-3 py-2 text-right">Valor</th>
                    <th class="px-3 py-2 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($receitas as $receita)
                    <tr>
                        <td class="px-3 py-2">{{ $receita->data->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            {{ $receita->descricao }}
                            @if ($receita->cobrancaExtra)
                                <span class="ml-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{{ $receita->cobrancaExtra->nome }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($receita->valor) }}</td>
                        <td class="px-3 py-2 text-right">
                            @can('update', $receita)
                                <button type="button" wire:click="editar({{ $receita->id }})"
                                        class="rounded-md bg-brand px-3 py-1 text-xs font-medium text-white hover:bg-brand-dark">Editar</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-gray-500">Nenhum registro encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <th colspan="2" class="px-3 py-2 text-left">Total:</th>
                    <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($total) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        <div class="mt-4">{{ $receitas->links() }}</div>
    </div>
</div>
