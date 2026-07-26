<div class="card">
    <h1 class="page-title mb-6">
        @if ($proprietario)
            Editar Proprietário: {{ $proprietario->nome }}
        @else
            Cadastrar Proprietário
        @endif
    </h1>

    <form wire:submit="salvar">
        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
            {{-- Coluna Proprietário --}}
            <div class="space-y-4">
                <h2 class="section-label">Dados do Proprietário</h2>

                <div>
                    <label for="nome" class="label">Nome Completo *</label>
                    <input id="nome" type="text" wire:model="nome"
                           class="input">
                    @error('nome') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cpf" class="label">CPF *</label>
                    <input id="cpf" type="text" wire:model="cpf" maxlength="14" placeholder="000.000.000-00" inputmode="numeric"
                           oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')"
                           class="input">
                    @error('cpf') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="telefone" class="label">Telefone *</label>
                    <input id="telefone" type="text" wire:model="telefone" maxlength="20"
                           class="input">
                    @error('telefone') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="responsavel_pagamento" class="label">Quem é o responsável pelo pagamento?</label>
                    <select id="responsavel_pagamento" wire:model="responsavel_pagamento"
                            class="input">
                        @foreach ($responsaveis as $responsavel)
                            <option value="{{ $responsavel->value }}">{{ $responsavel->rotulo() }}</option>
                        @endforeach
                    </select>
                    @error('responsavel_pagamento') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Coluna Inquilino --}}
            <div class="space-y-4">
                <h2 class="section-label">Dados do Inquilino (Opcional)</h2>

                <div>
                    <label for="nome_inquilino" class="label">Nome do Inquilino</label>
                    <input id="nome_inquilino" type="text" wire:model="nome_inquilino"
                           class="input">
                    @error('nome_inquilino') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cpf_inquilino" class="label">CPF do Inquilino</label>
                    <input id="cpf_inquilino" type="text" wire:model="cpf_inquilino" maxlength="14" placeholder="000.000.000-00" inputmode="numeric"
                           oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')"
                           class="input">
                    @error('cpf_inquilino') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="telefone_inquilino" class="label">Telefone do Inquilino</label>
                    <input id="telefone_inquilino" type="text" wire:model="telefone_inquilino" maxlength="20"
                           class="input">
                    @error('telefone_inquilino') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-2">
            <a href="{{ route('proprietarios.index') }}"
               class="btn btn-secondary">
                Voltar
            </a>
            <button type="submit" wire:loading.attr="disabled"
                    class="btn btn-primary">
                {{ $proprietario ? 'Atualizar' : 'Salvar' }}
            </button>
        </div>
    </form>
</div>
