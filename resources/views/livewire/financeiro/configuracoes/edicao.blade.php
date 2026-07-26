<div class="space-y-6">
    <div class="card">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Configurações do condomínio</h2>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        <form wire:submit="salvar" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Nome do condomínio" wire:model="nomeCondominio" />
                <x-input label="Taxa de mensalidade padrão (R$)" wire:model="taxaMensalidadePadrao"
                         placeholder="0,00" class="text-right" />
                <x-input label="Ano inicial do filtro de pagamentos" type="number" wire:model="anoInicialFiltroPagamentos" />
                <x-select label="Método de correção monetária" wire:model="metodoCorrecao">
                    @foreach ($metodos as $metodo)
                        <option value="{{ $metodo->value }}">{{ $metodo->rotulo() }}</option>
                    @endforeach
                </x-select>
                <x-input label="Subtítulo do recibo" wire:model="subtituloRecibo" />
                <x-input label="Assinatura do recibo" wire:model="assinaturaRecibo" />
            </div>
            <p class="text-xs text-slate-500">
                A data de corte (level one) não existe mais — o acesso é controlado pelos papéis
                de usuário (administrador, síndico, proprietário).
            </p>
            <div>
                <x-button type="submit">Gravar</x-button>
            </div>
        </form>
    </div>
</div>
