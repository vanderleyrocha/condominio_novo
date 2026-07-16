<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Grade Anual — {{ $ano }}</h1>
        <button type="button" wire:click="gravar" wire:loading.attr="disabled"
                class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
            Gravar
        </button>
    </div>

    @if ($erro !== '')
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $erro }}</div>
    @endif

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-3">Imóvel</th>
                    @foreach (\App\Support\MesesBr::abreviados() as $abreviado)
                        <th class="px-2 py-3 text-center">{{ $abreviado }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($imoveis as $imovel)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-2 font-medium">{{ $imovel->nome }}</td>
                        @php
                            $porMes = $imovel->mensalidades->keyBy('mes');
                        @endphp
                        @for ($mes = 1; $mes <= 12; $mes++)
                            @php
                                $mensalidade = $porMes->get($mes);
                                $classeStatus = $mensalidade !== null
                                    ? $mensalidade->status()->classeGrade((bool) $mensalidade->contabilizado)
                                    : '';
                            @endphp
                            <td class="px-1 py-1 text-center {{ $classeStatus }}">
                                @if ($mensalidade !== null)
                                    <input type="number" step="0.01" min="0"
                                           wire:model="valores.{{ $mensalidade->id }}"
                                           class="w-20 rounded border border-gray-300 bg-transparent px-1 py-1 text-right text-xs focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                                @else
                                    <span class="text-gray-300">-</span>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="px-4 py-6 text-center text-gray-500">
                            Nenhuma mensalidade encontrada para o ano de {{ $ano }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        <button type="button" wire:click="gravar" wire:loading.attr="disabled"
                class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
            Gravar
        </button>
    </div>
</div>
