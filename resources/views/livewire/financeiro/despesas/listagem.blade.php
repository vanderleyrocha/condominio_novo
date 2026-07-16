<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Ano</label>
                <input type="number" wire:model.live="ano"
                       class="mt-1 w-28 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
            </div>
            <div class="w-56">
                <label class="block text-sm font-medium text-gray-700">Tipo</label>
                <select wire:model.live="tipo"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                    <option value="0">Todos</option>
                    @foreach ($tipos as $id => $descricao)
                        <option value="{{ $id }}">{{ $descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-64 flex-1">
                <label class="block text-sm font-medium text-gray-700">Pesquisar</label>
                <input type="text" wire:model.live.debounce.400ms="busca"
                       placeholder="Valor, mês (ex.: Janeiro), data (DD/MM/AAAA) ou descrição/tipo"
                       class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
            </div>
            @can('create', [App\Models\Despesa::class, now()->toDateString()])
                <button type="button" wire:click="novaDespesa"
                        class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Nova despesa</button>
            @endcan
        </div>
        <div class="mt-4 flex flex-wrap items-end gap-4 border-t border-gray-100 pt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Data inicial</label>
                <input type="date" wire:model="dataInicial"
                       class="mt-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Data final</label>
                <input type="date" wire:model="dataFinal"
                       class="mt-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
            </div>
            <a href="{{ route('pdf.despesas', ['data_inicial' => $dataInicial, 'data_final' => $dataFinal]) }}"
               target="_blank"
               class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Imprimir</a>
        </div>
    </div>

    @if ($mensagem !== '')
        <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ $mensagem }}</div>
    @endif
    @if ($erro !== '')
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $erro }}</div>
    @endif

    @if ($formAberto)
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="mb-4 text-base font-semibold text-gray-900">
                {{ $despesaId === null ? 'Nova despesa' : 'Editar despesa' }}
            </h3>
            <form wire:submit="salvar" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select wire:model="formTipoId"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                            <option value="">Selecione...</option>
                            @foreach ($tipos as $id => $descricao)
                                <option value="{{ $id }}">{{ $descricao }}</option>
                            @endforeach
                        </select>
                        @error('formTipoId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <div class="mt-2 flex gap-2">
                            <input type="text" wire:model="novoTipo" placeholder="Criar novo tipo..."
                                   class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-xs focus:border-brand focus:ring-brand">
                            <button type="button" wire:click="criarTipo"
                                    class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Adicionar</button>
                        </div>
                        @error('novoTipo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data</label>
                        <input type="date" wire:model="formData"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                        @error('formData') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valor</label>
                        <input type="text" wire:model="formValor" placeholder="0,00"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-right focus:border-brand focus:ring-brand">
                        @error('formValor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Descrição</label>
                        <input type="text" wire:model="formDescricao"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                        @error('formDescricao') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @can('gerenciarContabilizado', App\Models\Despesa::class)
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
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Descrição</th>
                    <th class="px-3 py-2 text-right">Valor</th>
                    <th class="px-3 py-2 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($despesas as $despesa)
                    @php
                        // Cores de auditoria admin (DEV-T-14/15/16)
                        $classe = '';
                        if (auth()->user()->isAdmin()) {
                            if ($despesa->created_at != $despesa->updated_at) {
                                $classe = $despesa->contabilizado ? 'bg-yellow-100' : 'bg-gray-100';
                            } elseif (! $despesa->contabilizado) {
                                $classe = 'bg-green-100';
                            }
                        }
                    @endphp
                    <tr class="{{ $classe }}">
                        <td class="px-3 py-2">{{ $despesas->firstItem() + $loop->index }}</td>
                        <td class="px-3 py-2">{{ $despesa->data->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            {{ $despesa->descricao }}
                            @if ($despesa->tipo)
                                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $despesa->tipo->descricao }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($despesa->valor) }}</td>
                        <td class="px-3 py-2 text-right">
                            @if (auth()->user()->isAdmin())
                                <button type="button" wire:click="editar({{ $despesa->id }})"
                                        class="rounded-md bg-brand px-3 py-1 text-xs font-medium text-white hover:bg-brand-dark">Editar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">Nenhum registro encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <th colspan="3" class="px-3 py-2 text-left">Total</th>
                    <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($total) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        <div class="mt-4">{{ $despesas->links() }}</div>
    </div>
</div>
