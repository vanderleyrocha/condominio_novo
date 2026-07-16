<div class="rounded-lg bg-white p-6 shadow">
    <h1 class="mb-6 text-lg font-semibold">
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
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dados do Proprietário</h2>

                <div>
                    <label for="nome" class="mb-1 block text-sm font-medium text-gray-700">Nome Completo *</label>
                    <input id="nome" type="text" wire:model="nome"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('nome') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cpf" class="mb-1 block text-sm font-medium text-gray-700">CPF *</label>
                    <input id="cpf" type="text" wire:model="cpf" maxlength="14" placeholder="000.000.000-00" inputmode="numeric"
                           oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('cpf') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="telefone" class="mb-1 block text-sm font-medium text-gray-700">Telefone *</label>
                    <input id="telefone" type="text" wire:model="telefone" maxlength="20"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('telefone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="responsavel_pagamento" class="mb-1 block text-sm font-medium text-gray-700">Quem é o responsável pelo pagamento?</label>
                    <select id="responsavel_pagamento" wire:model="responsavel_pagamento"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                        @foreach ($responsaveis as $responsavel)
                            <option value="{{ $responsavel->value }}">{{ $responsavel->rotulo() }}</option>
                        @endforeach
                    </select>
                    @error('responsavel_pagamento') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Coluna Inquilino --}}
            <div class="space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dados do Inquilino (Opcional)</h2>

                <div>
                    <label for="nome_inquilino" class="mb-1 block text-sm font-medium text-gray-700">Nome do Inquilino</label>
                    <input id="nome_inquilino" type="text" wire:model="nome_inquilino"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('nome_inquilino') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cpf_inquilino" class="mb-1 block text-sm font-medium text-gray-700">CPF do Inquilino</label>
                    <input id="cpf_inquilino" type="text" wire:model="cpf_inquilino" maxlength="14" placeholder="000.000.000-00" inputmode="numeric"
                           oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('cpf_inquilino') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="telefone_inquilino" class="mb-1 block text-sm font-medium text-gray-700">Telefone do Inquilino</label>
                    <input id="telefone_inquilino" type="text" wire:model="telefone_inquilino" maxlength="20"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    @error('telefone_inquilino') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-2">
            <a href="{{ route('proprietarios.index') }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                Voltar
            </a>
            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark disabled:opacity-50">
                {{ $proprietario ? 'Atualizar' : 'Salvar' }}
            </button>
        </div>
    </form>
</div>
