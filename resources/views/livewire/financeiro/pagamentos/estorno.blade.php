<div class="mx-auto max-w-3xl space-y-6">
    <h1 class="page-title">Estornar Pagamento #{{ $pagamento->id }}</h1>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    <div class="card">
        <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <p><span class="font-medium text-slate-700">Data:</span> {{ $pagamento->data_pagamento?->format('d/m/Y') ?? '-' }}</p>
            <p><span class="font-medium text-slate-700">Valor:</span> R$ {{ \App\Support\DinheiroBr::formatar($pagamento->valor_total) }}</p>
            <p><span class="font-medium text-slate-700">Pagador:</span> {{ $pagamento->pessoa->nome ?? '-' }}</p>
            <p><span class="font-medium text-slate-700">Unidade:</span> {{ $pagamento->unidade->identificacao ?? '-' }}</p>
        </div>
    </div>

    <div class="card">
        <h2 class="section-label mb-4">Valores a estornar por taxa</h2>
        <x-table>
            <x-slot:head>
                <tr>
                    <th>Competência</th>
                    <th class="text-right">Valor pago</th>
                    <th class="text-right">Valor a estornar</th>
                </tr>
            </x-slot:head>
            @foreach ($pagamento->taxasCondominiais as $taxa)
                <tr wire:key="est-taxa-{{ $taxa->id }}">
                    <td>{{ \App\Support\MesesBr::nome((int) $taxa->competencia_mes) }}/{{ $taxa->competencia_ano }}</td>
                    <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($taxa->pivot->valor_aplicado) }}</td>
                    <td class="text-right">
                        <input type="number" step="0.01" min="0" wire:model.live="valores.{{ $taxa->id }}"
                               class="input w-28 text-right">
                        @error("valores.{$taxa->id}") <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </td>
                </tr>
            @endforeach
            <x-slot:foot>
                <tr class="font-semibold">
                    <th colspan="2" class="text-left">Total a estornar</th>
                    <th class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($this->totalAEstornar) }}</th>
                </tr>
            </x-slot:foot>
        </x-table>
    </div>

    <div class="flex items-center gap-3">
        <x-button variant="danger" wire:click="confirmar" wire:loading.attr="disabled"
                  wire:confirm="Confirmar o estorno deste pagamento?">
            Confirmar estorno
        </x-button>
        <a href="{{ route('pagamentos.show', $pagamento) }}" class="text-sm text-slate-500 hover:underline">Cancelar</a>
    </div>
</div>
