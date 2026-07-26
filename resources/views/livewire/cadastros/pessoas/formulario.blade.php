<div class="card">
    <h1 class="page-title mb-6">
        @if ($pessoa)
            Editar Pessoa: {{ $pessoa->nome }}
        @else
            Cadastrar Pessoa
        @endif
    </h1>

    <form wire:submit="salvar">
        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
            <div class="space-y-4">
                <x-input label="Nome Completo / Razão Social *" wire:model="nome" />

                <x-select label="Tipo *" wire:model="tipo">
                    @foreach ($tipos as $opcao)
                        <option value="{{ $opcao->value }}">{{ $opcao->rotulo() }}</option>
                    @endforeach
                </x-select>

                <x-input label="CPF ou CNPJ" wire:model="cpf_cnpj" maxlength="18" inputmode="numeric"
                         placeholder="000.000.000-00 ou 00.000.000/0000-00"
                         help="Opcional — pessoas do legado sem CPF podem ser completadas depois."
                         oninput="const d = this.value.replace(/\D/g, '').slice(0, 14);
                                  this.value = d.length <= 11
                                      ? d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')
                                      : d.replace(/(\d{2})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');" />
            </div>

            <div class="space-y-4">
                <x-input label="E-mail" type="email" wire:model="email"
                         help="Necessário para vincular uma conta de acesso de proprietário." />

                <x-input label="Telefone" wire:model="telefone" maxlength="20" />
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-2">
            <x-button variant="secondary" :href="route('pessoas.index')">Voltar</x-button>
            <x-button type="submit" wire:loading.attr="disabled">
                {{ $pessoa ? 'Atualizar' : 'Salvar' }}
            </x-button>
        </div>
    </form>
</div>
