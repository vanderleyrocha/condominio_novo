<div class="mx-auto max-w-4xl space-y-6">
    <h1 class="text-xl font-semibold">Novo Pagamento</h1>

    @if ($erro !== '')
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $erro }}</div>
    @endif

    <form wire:submit="salvar" class="space-y-6">
        {{-- Dados do pagamento --}}
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Dados do Pagamento</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="data" class="mb-1 block text-sm font-medium text-gray-700">Data</label>
                    <input id="data" type="date" wire:model="data"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('data') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="valor" class="mb-1 block text-sm font-medium text-gray-700">Valor do pagamento</label>
                    <input id="valor" type="text" wire:model.live.debounce.500ms="valor" inputmode="decimal" placeholder="0,00"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('valor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="descricao" class="mb-1 block text-sm font-medium text-gray-700">Descrição</label>
                    <input id="descricao" type="text" wire:model="descricao"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('descricao') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="proprietarioId" class="mb-1 block text-sm font-medium text-gray-700">Proprietário</label>
                    <select id="proprietarioId" wire:model.live="proprietarioId"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                        <option value="">Selecione</option>
                        @foreach ($this->proprietarios as $proprietario)
                            <option value="{{ $proprietario->id }}">{{ $proprietario->nome }}</option>
                        @endforeach
                    </select>
                    @error('proprietarioId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Filtro por ano --}}
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Filtro por Ano</h2>
            <label class="mb-3 flex items-center gap-2 text-sm font-medium text-gray-700">
                <input type="checkbox" wire:model.live="todosAnos" class="rounded border-gray-300">
                Selecionar todos os anos
            </label>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                @foreach ($this->anosDisponiveis() as $anoDisponivel)
                    <label class="flex items-center gap-1.5 text-sm text-gray-600">
                        <input type="checkbox" wire:model.live="anos" value="{{ $anoDisponivel }}" class="rounded border-gray-300">
                        {{ $anoDisponivel }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Mensalidades em aberto --}}
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Mensalidades em Aberto</h2>

            @if (empty($anos))
                <p class="text-sm text-gray-600">Selecione ao menos um ano para visualizar as mensalidades.</p>
            @elseif ($this->mensalidadesEmAberto->isEmpty())
                <p class="text-sm text-gray-600">Nenhuma mensalidade em aberto encontrada.</p>
            @else
                @php $alocacao = $this->alocacao; @endphp
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-3"></th>
                                <th class="px-4 py-3">Ano</th>
                                <th class="px-4 py-3">Mês</th>
                                <th class="px-4 py-3">Vencimento</th>
                                <th class="px-4 py-3 text-right">Valor devido</th>
                                <th class="px-4 py-3 text-right">Valor a pagar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($this->mensalidadesEmAberto as $mensalidade)
                                @php
                                    $aplicado = $alocacao['linhas'][$mensalidade->id] ?? null;
                                    $devido = (float) $mensalidade->valorDevido();
                                    $classeLinha = '';
                                    if ($aplicado !== null && $aplicado > 0) {
                                        $classeLinha = $aplicado < $devido ? 'bg-status-parcial' : 'bg-teal-50';
                                    }
                                @endphp
                                <tr class="{{ $classeLinha }}">
                                    <td class="px-4 py-2">
                                        <input type="checkbox" wire:model.live="selecionadas" value="{{ $mensalidade->id }}"
                                               class="rounded border-gray-300">
                                    </td>
                                    <td class="px-4 py-2">{{ $mensalidade->ano }}</td>
                                    <td class="px-4 py-2">{{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</td>
                                    <td class="px-4 py-2">{{ $mensalidade->vencimento?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right">R$ {{ \App\Support\DinheiroBr::formatar($devido) }}</td>
                                    <td class="px-4 py-2 text-right">
                                        {{ $aplicado !== null ? 'R$ '.\App\Support\DinheiroBr::formatar($aplicado) : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="mt-4 text-right text-sm font-medium text-gray-700">
                    Saldo restante: <span class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($alocacao['saldo']) }}</span>
                </p>
            @endif
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
                <span wire:loading.remove wire:target="salvar">Salvar pagamento</span>
                <span wire:loading wire:target="salvar">Salvando...</span>
            </button>
        </div>
    </form>
</div>
