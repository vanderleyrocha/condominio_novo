<div class="space-y-6">
    <div class="card">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Parâmetros do condomínio</h2>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        <form wire:submit="salvar" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="label">Nome do condomínio</label>
                    <input type="text" wire:model="nomeCondominio"
                           class="input">
                    @error('nomeCondominio') <p class="error-text text-xs">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Taxa de mensalidade padrão (R$)</label>
                    <input type="text" wire:model="taxaMensalidadePadrao" placeholder="0,00"
                           class="input text-right">
                    @error('taxaMensalidadePadrao') <p class="error-text text-xs">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Data de corte (level one)</label>
                    <input type="date" wire:model="dataCorteLevelOne"
                           class="input">
                    @error('dataCorteLevelOne') <p class="error-text text-xs">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Ano inicial do filtro de pagamentos</label>
                    <input type="number" wire:model="anoInicialFiltroPagamentos"
                           class="input">
                    @error('anoInicialFiltroPagamentos') <p class="error-text text-xs">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Subtítulo do recibo</label>
                    <input type="text" wire:model="subtituloRecibo"
                           class="input">
                    @error('subtituloRecibo') <p class="error-text text-xs">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Assinatura do recibo</label>
                    <input type="text" wire:model="assinaturaRecibo"
                           class="input">
                    @error('assinaturaRecibo') <p class="error-text text-xs">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Método de correção monetária</label>
                    <select wire:model="metodoCorrecao"
                            class="input">
                        @foreach ($metodos as $metodo)
                            <option value="{{ $metodo->value }}">{{ $metodo->rotulo() }}</option>
                        @endforeach
                    </select>
                    @error('metodoCorrecao') <p class="error-text text-xs">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <button type="submit"
                        class="btn btn-primary">Gravar</button>
            </div>
        </form>
    </div>
</div>
