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
            <div>
                <label for="vencimento" class="label">Data de vencimento</label>
                <input id="vencimento" type="date" wire:model="vencimento" class="input">
                @error('vencimento') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="valorOriginal" class="label">Valor devido</label>
                <input id="valorOriginal" type="text" wire:model="valorOriginal" inputmode="decimal" placeholder="0,00" class="input">
                @error('valorOriginal') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="acrescimo" class="label">Acréscimo</label>
                <input id="acrescimo" type="text" wire:model="acrescimo" inputmode="decimal" placeholder="0,00" class="input">
                @error('acrescimo') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="desconto" class="label">Desconto</label>
                <input id="desconto" type="text" wire:model="desconto" inputmode="decimal" placeholder="0,00" class="input">
                @error('desconto') <p class="error-text">{{ $message }}</p> @enderror
            </div>

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
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">
                    <span wire:loading.remove>Salvar</span>
                    <span wire:loading>Salvando...</span>
                </button>
                @if ((float) $valorPago > 0)
                    <a href="{{ route('pdf-novo.taxas.recibo', $taxa) }}" target="_blank" class="btn btn-secondary">
                        Recibo
                    </a>
                @endif
                <a href="{{ route('taxas.index', ['ano' => $taxa->competencia_ano, 'unidade' => $taxa->unidade_id]) }}"
                   class="text-sm text-slate-500 hover:underline">Voltar</a>
            </div>
        </form>
    </div>
</div>
