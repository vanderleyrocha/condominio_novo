<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="page-title">Editar Taxa Condominial</h1>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    <div class="card">
        <div class="mb-6 grid grid-cols-1 gap-4 border-b border-slate-100 pb-4 text-sm sm:grid-cols-3">
            <p><span class="font-medium text-slate-700">Unidade:</span> {{ $taxa->unidade->identificacao ?? '-' }}</p>
            <p><span class="font-medium text-slate-700">Competência:</span> {{ \App\Support\MesesBr::nome((int) $taxa->competencia_mes) }}/{{ $taxa->competencia_ano }}</p>
            <p><span class="font-medium text-slate-700">Status:</span> {{ $taxa->status->rotulo() }}</p>
        </div>

        <form wire:submit="salvar" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-input label="Data de vencimento" type="date" wire:model="vencimento" />

            <x-input label="Valor devido" wire:model="valorOriginal" inputmode="decimal" placeholder="0,00" />

            <x-input label="Acréscimo" wire:model="acrescimo" inputmode="decimal" placeholder="0,00" />

            <x-input label="Desconto" wire:model="desconto" inputmode="decimal" placeholder="0,00" />

            <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                <span class="font-medium text-slate-700">Valor pago (derivado dos pagamentos):</span>
                R$ {{ \App\Support\DinheiroBr::formatar($valorPago) }}
                <p class="mt-1 text-xs text-slate-500">
                    Pagamentos são registrados no módulo de pagamentos ou pela grade anual —
                    o status desta taxa é recalculado automaticamente.
                </p>
            </div>

            <div class="sm:col-span-2">
                @can('gerenciarContabilizado', \App\Models\TaxaCondominial::class)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="contabilizado" class="checkbox">
                        Contabilizada
                    </label>
                @else
                    <label class="flex items-center gap-2 text-sm text-slate-400">
                        <input type="checkbox" checked disabled class="checkbox">
                        Contabilizada
                    </label>
                @endcan
            </div>

            <div class="flex items-center gap-3 sm:col-span-2">
                <x-button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Salvar</span>
                    <span wire:loading>Salvando...</span>
                </x-button>
                @if ((float) $valorPago > 0)
                    <x-button variant="secondary" :href="route('pdf.taxas.recibo', $taxa)" target="_blank">Recibo</x-button>
                @endif
                <a href="{{ route('taxas.index', ['ano' => $taxa->competencia_ano, 'unidade' => $taxa->unidade_id]) }}"
                   class="text-sm text-slate-500 hover:underline">Voltar</a>
            </div>
        </form>
    </div>
</div>
