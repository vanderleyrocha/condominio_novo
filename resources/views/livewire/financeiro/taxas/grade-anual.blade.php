<div class="space-y-4">
    <x-page-header title="Grade Anual — {{ $ano }}">
        <x-button wire:click="gravar" wire:loading.attr="disabled">Gravar</x-button>
    </x-page-header>

    <p class="text-xs text-slate-500">
        A célula mostra o total pago da taxa. Alterar o valor gera um pagamento (aumento)
        ou um ajuste (redução) atribuído ao responsável financeiro da unidade.
    </p>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Unidade</th>
                    @foreach (\App\Support\MesesBr::abreviados() as $abreviado)
                        <th class="px-2 text-center">{{ $abreviado }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($unidades as $unidade)
                    <tr wire:key="grade-unidade-{{ $unidade->id }}">
                        <td class="whitespace-nowrap font-medium">{{ $unidade->identificacao }}</td>
                        @php
                            $porMes = $unidade->taxasCondominiais->keyBy('competencia_mes');
                        @endphp
                        @for ($mes = 1; $mes <= 12; $mes++)
                            @php
                                $taxa = $porMes->get($mes);
                                $classeStatus = $taxa !== null
                                    ? $taxa->status->classeGrade((bool) $taxa->contabilizado, $taxa->vencida())
                                    : '';
                            @endphp
                            <td class="px-1 py-1 text-center {{ $classeStatus }}">
                                @if ($taxa !== null)
                                    <input type="number" step="0.01" min="0"
                                           wire:model="valores.{{ $taxa->id }}"
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
                            Nenhuma taxa encontrada para o ano de {{ $ano }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        <button type="button" wire:click="gravar" wire:loading.attr="disabled" class="btn btn-primary">
            Gravar
        </button>
    </div>
</div>
