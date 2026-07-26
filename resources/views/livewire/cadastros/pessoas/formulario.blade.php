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
                <div>
                    <label for="nome" class="label">Nome Completo / Razão Social *</label>
                    <input id="nome" type="text" wire:model="nome" class="input">
                    @error('nome') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="tipo" class="label">Tipo *</label>
                    <select id="tipo" wire:model="tipo" class="input">
                        @foreach ($tipos as $opcao)
                            <option value="{{ $opcao->value }}">{{ $opcao->rotulo() }}</option>
                        @endforeach
                    </select>
                    @error('tipo') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cpf_cnpj" class="label">CPF ou CNPJ</label>
                    <input id="cpf_cnpj" type="text" wire:model="cpf_cnpj" maxlength="18" inputmode="numeric"
                           placeholder="000.000.000-00 ou 00.000.000/0000-00"
                           oninput="const d = this.value.replace(/\D/g, '').slice(0, 14);
                                    this.value = d.length <= 11
                                        ? d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')
                                        : d.replace(/(\d{2})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');">
                    <p class="mt-1 text-xs text-slate-500">Opcional — pessoas do legado sem CPF podem ser completadas depois.</p>
                    @error('cpf_cnpj') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label for="email" class="label">E-mail</label>
                    <input id="email" type="email" wire:model="email" class="input">
                    <p class="mt-1 text-xs text-slate-500">Necessário para vincular uma conta de acesso de proprietário.</p>
                    @error('email') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="telefone" class="label">Telefone</label>
                    <input id="telefone" type="text" wire:model="telefone" maxlength="20" class="input">
                    @error('telefone') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-2">
            <a href="{{ route('pessoas.index') }}" class="btn btn-secondary">
                Voltar
            </a>
            <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">
                {{ $pessoa ? 'Atualizar' : 'Salvar' }}
            </button>
        </div>
    </form>
</div>
