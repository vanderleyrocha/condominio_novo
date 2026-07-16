<div class="rounded-lg bg-white p-6 shadow">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Imóveis</h1>
        @can('create', \App\Models\Imovel::class)
            <button type="button" wire:click="novo"
                    class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark">
                Novo Imóvel
            </button>
        @endcan
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-brand">
                    <th class="px-3 py-2 font-semibold">Imóvel</th>
                    <th class="px-3 py-2 font-semibold">Proprietário</th>
                    <th class="px-3 py-2 font-semibold">Mensalidades</th>
                    @can('create', \App\Models\Imovel::class)
                        <th class="px-3 py-2 text-right font-semibold">Ações</th>
                    @endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($imoveis as $imovel)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2">{{ $imovel->nome }}</td>
                        <td class="px-3 py-2">{{ $imovel->proprietario->nome ?? 'Não informado' }}</td>
                        <td class="px-3 py-2">{{ $imovel->mensalidades_count }}</td>
                        @can('update', $imovel)
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="editar({{ $imovel->id }})" title="Editar"
                                        class="inline-block rounded-md px-2 py-1 text-brand hover:bg-blue-50">
                                    Editar
                                </button>
                                <button type="button" wire:click="confirmarExclusao({{ $imovel->id }})" title="Excluir"
                                        class="inline-block rounded-md px-2 py-1 text-red-600 hover:bg-red-50">
                                    Excluir
                                </button>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-gray-500">Nenhum imóvel cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal de formulário (novo/editar) --}}
    @if ($exibirFormulario)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:keydown.escape.window="cancelar">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow">
                <h2 class="mb-4 text-lg font-semibold">{{ $imovelId ? 'Editar Imóvel' : 'Novo Imóvel' }}</h2>

                <form wire:submit="salvar" class="space-y-4">
                    <div>
                        <label for="form-nome" class="mb-1 block text-sm font-medium text-gray-700">Nome *</label>
                        <input id="form-nome" type="text" wire:model="nome"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                        @error('nome') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="form-proprietario" class="mb-1 block text-sm font-medium text-gray-700">Proprietário *</label>
                        <select id="form-proprietario" wire:model="proprietario_id"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                            <option value="">Selecione</option>
                            @foreach ($proprietarios as $proprietario)
                                <option value="{{ $proprietario->id }}">{{ $proprietario->nome }}</option>
                            @endforeach
                        </select>
                        @error('proprietario_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="cancelar"
                                class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                                class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal de confirmação de exclusão --}}
    @if ($confirmandoExclusaoId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:keydown.escape.window="cancelarExclusao">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow">
                <p class="mb-6 text-sm text-gray-700">Tem certeza que deseja excluir este imóvel?</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelarExclusao"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="excluir" wire:loading.attr="disabled"
                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
