<div class="mx-auto max-w-lg space-y-6">
    <h1 class="page-title">Lançar Mensalidades</h1>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    <div class="card">
        <form wire:submit="lancar" class="space-y-4">
            <div>
                <label for="ano" class="label">Ano de referência</label>
                <input id="ano" type="number" wire:model="ano" min="2000" max="2100"
                       class="input">
                @error('ano') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="valor" class="label">Valor devido</label>
                <input id="valor" type="text" wire:model="valor" inputmode="decimal" placeholder="0,00"
                       class="input">
                @error('valor') <p class="error-text">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-500">Serão lançadas 12 mensalidades para cada imóvel cadastrado, com vencimento no último dia de cada mês.</p>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="btn btn-primary">
                <span wire:loading.remove>Salvar</span>
                <span wire:loading>Salvando...</span>
            </button>
        </form>
    </div>
</div>
