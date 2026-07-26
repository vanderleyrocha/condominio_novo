<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Grade Anual — {{ $ano }}</h1>
        <button type="button" wire:click="gravar" wire:loading.attr="disabled"
                class="btn btn-primary">
            Gravar
        </button>
    </div>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Imóvel</th>
                    @foreach (\App\Support\MesesBr::abreviados() as $abreviado)
                        <th class="px-2 text-center">{{ $abreviado }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($imoveis as $imovel)
                    <tr>
                        <td class="whitespace-nowrap font-medium">{{ $imovel->nome }}</td>
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
                                           class="w-20 rounded-md border border-slate-400/40 bg-white/70 px-1.5 py-1 text-right text-xs shadow-sm transition focus:border-brand focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/25">
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="py-6 text-center text-slate-500">
                            Nenhuma mensalidade encontrada para o ano de {{ $ano }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        <button type="button" wire:click="gravar" wire:loading.attr="disabled"
                class="btn btn-primary">
            Gravar
        </button>
    </div>
</div>
