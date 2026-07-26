<div class="space-y-6">
    <div class="card">
        <x-page-header title="Cobranças Extraordinárias" class="mb-4">
            <x-button wire:click="novaCobranca">Nova cobrança</x-button>
        </x-page-header>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-4 text-base font-semibold text-slate-900">
                    {{ $cobrancaId === null ? 'Nova cobrança' : 'Editar cobrança' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <x-input label="Nome" wire:model="formNome" />
                    <x-input label="Valor total (R$)" wire:model="formValor" placeholder="0,00" class="text-right" />
                    <x-select label="Método de rateio" wire:model="formMetodoRateio">
                        @foreach ($metodos as $metodo)
                            <option value="{{ $metodo->value }}">{{ $metodo->rotulo() }}</option>
                        @endforeach
                    </x-select>
                    <x-input label="Vigência início" type="date" wire:model="formVigenciaInicio" />
                    <x-input label="Vigência fim (vazio = permanente)" type="date" wire:model="formVigenciaFim" />
                    <div class="flex items-end pb-2">
                        <label class="label flex items-center gap-2">
                            <input type="checkbox" wire:model="formAtiva" class="checkbox">
                            Ativa
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <x-button type="submit">Gravar</x-button>
                    <x-button variant="secondary" wire:click="cancelar">Cancelar</x-button>
                </div>
            </form>
        @endif

        <x-table class="-mx-6 px-6">
            <x-slot:head>
                <tr>
                    <th>Nome</th>
                    <th class="text-right">Valor total</th>
                    <th>Rateio</th>
                    <th>Vigência</th>
                    <th>Ativa</th>
                    <th class="text-right">Apurado (taxas)</th>
                    <th class="text-right">Apurado (receitas)</th>
                    <th class="text-right">Ações</th>
                </tr>
            </x-slot:head>
            @forelse ($cobrancas as $cobranca)
                <tr wire:key="cobranca-{{ $cobranca->id }}">
                    <td>{{ $cobranca->nome }}</td>
                    <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($cobranca->valor_total) }}</td>
                    <td>{{ $cobranca->metodo_rateio->rotulo() }}</td>
                    <td class="text-sm">
                        {{ $cobranca->vigencia_inicio->format('d/m/Y') }}
                        — {{ $cobranca->vigencia_fim?->format('d/m/Y') ?? 'permanente' }}
                    </td>
                    <td>{{ $cobranca->ativa ? 'Sim' : 'Não' }}</td>
                    <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($cobranca->total_taxas ?? 0) }}</td>
                    <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($receitasPorCobranca[$cobranca->id] ?? 0) }}</td>
                    <td class="text-right">
                        <x-table-action wire:click="editar({{ $cobranca->id }})">Editar</x-table-action>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="py-6 text-center text-slate-500">Nenhuma cobrança extraordinária cadastrada.</td>
                </tr>
            @endforelse
        </x-table>
    </div>
</div>
