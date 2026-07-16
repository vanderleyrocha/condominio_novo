<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-xl font-semibold">Editar Mensalidade</h1>

    @if ($erro !== '')
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $erro }}</div>
    @endif

    <div class="rounded-lg bg-white p-6 shadow">
        <div class="mb-6 grid grid-cols-1 gap-4 border-b border-gray-100 pb-4 text-sm sm:grid-cols-3">
            <p><span class="font-medium text-gray-700">Imóvel:</span> {{ $mensalidade->imovel->nome ?? '-' }}</p>
            <p><span class="font-medium text-gray-700">Mês de referência:</span> {{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</p>
            <p><span class="font-medium text-gray-700">Ano de referência:</span> {{ $mensalidade->ano }}</p>
        </div>

        <form wire:submit="salvar" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="vencimento" class="mb-1 block text-sm font-medium text-gray-700">Data de vencimento</label>
                <input id="vencimento" type="date" wire:model="vencimento"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                @error('vencimento') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="valor" class="mb-1 block text-sm font-medium text-gray-700">Valor devido</label>
                <input id="valor" type="text" wire:model="valor" inputmode="decimal" placeholder="0,00"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                @error('valor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="acrescimo" class="mb-1 block text-sm font-medium text-gray-700">Acréscimo</label>
                <input id="acrescimo" type="text" wire:model.blur="acrescimo" inputmode="decimal" placeholder="0,00"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                @error('acrescimo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="desconto" class="mb-1 block text-sm font-medium text-gray-700">Desconto</label>
                <input id="desconto" type="text" wire:model.blur="desconto" inputmode="decimal" placeholder="0,00"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                @error('desconto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="valorPago" class="mb-1 block text-sm font-medium text-gray-700">Valor pago</label>
                <input id="valorPago" type="text" wire:model="valorPago" inputmode="decimal" placeholder="0,00"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                @error('valorPago') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="pagoEm" class="mb-1 block text-sm font-medium text-gray-700">Data do pagamento</label>
                <input id="pagoEm" type="date" wire:model="pagoEm"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                @error('pagoEm') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                @can('gerenciarContabilizado', \App\Models\Mensalidade::class)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="contabilizado" class="rounded border-gray-300">
                        Contabilizada
                    </label>
                @else
                    <label class="flex items-center gap-2 text-sm text-gray-400">
                        <input type="checkbox" checked disabled class="rounded border-gray-300">
                        Contabilizada
                    </label>
                @endcan
            </div>

            <div class="flex items-center gap-3 sm:col-span-2">
                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
                    <span wire:loading.remove>Salvar</span>
                    <span wire:loading>Salvando...</span>
                </button>
                @if ((float) $mensalidade->valor_pago > 0)
                    <a href="{{ route('pdf.mensalidades.recibo', $mensalidade) }}" target="_blank"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        Recibo
                    </a>
                @endif
                <a href="{{ route('mensalidades.index', ['ano' => $mensalidade->ano, 'imovel' => $mensalidade->imovel_id]) }}"
                   class="text-sm text-gray-500 hover:underline">Voltar</a>
            </div>
        </form>
    </div>
</div>
