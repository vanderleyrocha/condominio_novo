<div class="mx-auto max-w-lg space-y-6">
    <h1 class="text-xl font-semibold">Lançar Mensalidades</h1>

    @if ($erro !== '')
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $erro }}</div>
    @endif

    <div class="rounded-lg bg-white p-6 shadow">
        <form wire:submit="lancar" class="space-y-4">
            <div>
                <label for="ano" class="mb-1 block text-sm font-medium text-gray-700">Ano de referência</label>
                <input id="ano" type="number" wire:model="ano" min="2000" max="2100"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                @error('ano') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="valor" class="mb-1 block text-sm font-medium text-gray-700">Valor devido</label>
                <input id="valor" type="text" wire:model="valor" inputmode="decimal" placeholder="0,00"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                @error('valor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-500">Serão lançadas 12 mensalidades para cada imóvel cadastrado, com vencimento no último dia de cada mês.</p>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
                <span wire:loading.remove>Salvar</span>
                <span wire:loading>Salvando...</span>
            </button>
        </form>
    </div>
</div>
