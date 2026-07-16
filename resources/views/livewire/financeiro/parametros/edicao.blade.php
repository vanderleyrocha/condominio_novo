<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="mb-4 text-lg font-semibold text-gray-900">Parâmetros do condomínio</h2>

        @if ($mensagem !== '')
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ $mensagem }}</div>
        @endif

        <form wire:submit="salvar" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nome do condomínio</label>
                    <input type="text" wire:model="nomeCondominio"
                           class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                    @error('nomeCondominio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Taxa de mensalidade padrão (R$)</label>
                    <input type="text" wire:model="taxaMensalidadePadrao" placeholder="0,00"
                           class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-right focus:border-brand focus:ring-brand">
                    @error('taxaMensalidadePadrao') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Data de corte (level one)</label>
                    <input type="date" wire:model="dataCorteLevelOne"
                           class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                    @error('dataCorteLevelOne') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ano inicial do filtro de pagamentos</label>
                    <input type="number" wire:model="anoInicialFiltroPagamentos"
                           class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                    @error('anoInicialFiltroPagamentos') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Subtítulo do recibo</label>
                    <input type="text" wire:model="subtituloRecibo"
                           class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                    @error('subtituloRecibo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Assinatura do recibo</label>
                    <input type="text" wire:model="assinaturaRecibo"
                           class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                    @error('assinaturaRecibo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Método de correção monetária</label>
                    <select wire:model="metodoCorrecao"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                        @foreach ($metodos as $metodo)
                            <option value="{{ $metodo->value }}">{{ $metodo->rotulo() }}</option>
                        @endforeach
                    </select>
                    @error('metodoCorrecao') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <button type="submit"
                        class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Gravar</button>
            </div>
        </form>
    </div>
</div>
