<div class="space-y-6">
    <div class="card">
        <x-page-header title="Índices Econômicos" class="mb-4">
            <x-select wire:model.live="tipoFiltro" class="w-40" aria-label="Filtrar por série">
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo->value }}">{{ $tipo->rotulo() }}</option>
                @endforeach
            </x-select>
            <x-button wire:click="novoIndice">Novo índice</x-button>
        </x-page-header>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-4 text-base font-semibold text-slate-900">
                    {{ $indiceId === null ? 'Novo índice' : 'Editar índice' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-4">
                    <x-select label="Série" wire:model="formTipo">
                        @foreach ($tipos as $tipo)
                            <option value="{{ $tipo->value }}">{{ $tipo->rotulo() }}</option>
                        @endforeach
                    </x-select>
                    <x-input label="Ano" type="number" wire:model="formAno" />
                    <x-select label="Mês" wire:model="formMes">
                        <option value="">Selecione...</option>
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}">{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endforeach
                    </x-select>
                    <x-input label="Índice (%)" wire:model="formIndice" placeholder="0,0000" class="text-right" />
                </div>
                <div class="mt-4 flex gap-3">
                    <x-button type="submit">Gravar</x-button>
                    <x-button variant="secondary" wire:click="cancelar">Cancelar</x-button>
                </div>
            </form>
        @endif

        <x-table class="-mx-6 px-6">
            <x-slot:head>
                <tr>
                    <th>Série</th>
                    <th>Ano</th>
                    <th>Mês</th>
                    <th class="text-right">Índice (%)</th>
                    <th class="text-right">Ações</th>
                </tr>
            </x-slot:head>
            @forelse ($indices as $indice)
                <tr wire:key="indice-{{ $indice->id }}">
                    <td>{{ $indice->tipo->rotulo() }}</td>
                    <td>{{ $indice->ano }}</td>
                    <td>{{ str_pad((string) $indice->mes, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="text-right">{{ number_format((float) $indice->indice, 4, ',', '.') }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-2">
                            <x-table-action wire:click="editar({{ $indice->id }})">Editar</x-table-action>
                            <x-table-action variant="danger" wire:click="excluir({{ $indice->id }})"
                                            wire:confirm="Excluir o índice {{ $indice->tipo->rotulo() }} {{ str_pad((string) $indice->mes, 2, '0', STR_PAD_LEFT) }}/{{ $indice->ano }}?">
                                Excluir
                            </x-table-action>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-6 text-center text-slate-500">Nenhum índice cadastrado para esta série.</td>
                </tr>
            @endforelse
        </x-table>
        <div class="mt-4">{{ $indices->links() }}</div>
    </div>
</div>
