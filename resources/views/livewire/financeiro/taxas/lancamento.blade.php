<div class="mx-auto max-w-lg space-y-6">
    <h1 class="page-title">Lançar Taxas Condominiais</h1>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    <div class="card">
        <form wire:submit="lancar" class="space-y-4">
            <x-input label="Ano de referência" type="number" wire:model="ano" min="2000" max="2100" />

            <x-input label="Valor devido" wire:model="valor" inputmode="decimal" placeholder="0,00"
                     help="Serão lançadas 12 taxas para cada unidade cadastrada, com vencimento no último dia de cada mês." />

            <x-button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove>Salvar</span>
                <span wire:loading>Salvando...</span>
            </x-button>
        </form>
    </div>
</div>
