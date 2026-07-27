<div class="space-y-6">
    <div class="card">
        <x-page-header title="Cobranças Extraordinárias" class="mb-4">
            <x-button wire:click="novaCobranca">Nova cobrança</x-button>
        </x-page-header>

        <p class="mb-4 text-sm text-slate-500">
            Uma cobrança com <strong>valor por unidade</strong> definido é recorrente: ela vira um item na
            composição de cada mensalidade dentro da vigência. Sem esse valor, é apenas um alvo de arrecadação
            com rateio manual.
        </p>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        @if ($erro !== '')
            <div class="alert alert-danger mb-4">{{ $erro }}</div>
        @endif

        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-4 text-base font-semibold text-slate-900">
                    {{ $cobrancaId === null ? 'Nova cobrança' : 'Editar cobrança' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <x-input label="Nome" wire:model="formNome"
                             help="É a descrição que aparece na mensalidade do condômino." />
                    <x-input label="Valor total (R$)" wire:model="formValor" placeholder="0,00" class="text-right"
                             help="Alvo global da campanha." />
                    <x-input label="Valor por unidade (R$)" wire:model="formValorPorUnidade" placeholder="0,00" class="text-right"
                             help="Vazio = não gera item mensal." />
                    <x-select label="Finalidade" wire:model="formFinalidadeId"
                              help="Destinação da arrecadação.">
                        <option value="">— sem finalidade específica —</option>
                        @foreach ($finalidades as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Método de rateio" wire:model="formMetodoRateio">
                        @foreach ($metodos as $metodo)
                            <option value="{{ $metodo->value }}">{{ $metodo->rotulo() }}</option>
                        @endforeach
                    </x-select>
                    <div class="flex items-end pb-2">
                        <label class="label flex items-center gap-2">
                            <input type="checkbox" wire:model="formAtiva" class="checkbox">
                            Ativa
                        </label>
                    </div>
                    <x-input label="Vigência início" type="date" wire:model="formVigenciaInicio" />
                    <x-input label="Vigência fim (vazio = permanente)" type="date" wire:model="formVigenciaFim" />
                </div>
                <div class="mt-4 flex gap-3">
                    <x-button type="submit">Gravar</x-button>
                    <x-button variant="secondary" wire:click="cancelar">Cancelar</x-button>
                </div>
            </form>
        @endif

        @if ($aplicarCobrancaId !== null)
            <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-1 text-base font-semibold text-slate-900">Aplicar nas taxas já lançadas</h3>
                <p class="mb-4 text-sm text-slate-500">
                    Cria (ou remove) o item desta cobrança nas competências do intervalo. Competências que já
                    têm pagamento aplicado são ignoradas — estorne o pagamento antes de alterar o valor devido.
                </p>
                <div class="grid gap-4 md:grid-cols-4">
                    <x-input label="Ano inicial" type="number" wire:model="aplicarAnoInicio" />
                    <x-input label="Mês inicial" type="number" min="1" max="12" wire:model="aplicarMesInicio" />
                    <x-input label="Ano final" type="number" wire:model="aplicarAnoFim" />
                    <x-input label="Mês final" type="number" min="1" max="12" wire:model="aplicarMesFim" />
                </div>
                <div class="mt-4 flex flex-wrap gap-3">
                    <x-button wire:click="aplicarNasTaxas">Aplicar</x-button>
                    <x-button variant="secondary" wire:click="retirarDasTaxas"
                              wire:confirm="Remover o item desta cobrança das competências do intervalo?">
                        Retirar
                    </x-button>
                    <button type="button" wire:click="fecharAplicacao"
                            class="text-sm text-slate-500 hover:underline">Cancelar</button>
                </div>
            </div>
        @endif

        <x-table class="-mx-6 px-6">
            <x-slot:head>
                <tr>
                    <th>Nome</th>
                    <th>Finalidade</th>
                    <th class="text-right">Valor total</th>
                    <th class="text-right">Por unidade</th>
                    <th>Vigência</th>
                    <th>Ativa</th>
                    <th class="text-right">Cobrado (itens)</th>
                    <th class="text-right">Receitas</th>
                    <th class="text-right">Ações</th>
                </tr>
            </x-slot:head>
            @forelse ($cobrancas as $cobranca)
                <tr wire:key="cobranca-{{ $cobranca->id }}">
                    <td>{{ $cobranca->nome }}</td>
                    <td class="text-slate-500">{{ $cobranca->finalidade?->nome ?? '—' }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($cobranca->valor_total) }}</td>
                    <td class="text-right tabular-nums">
                        {{ $cobranca->valor_por_unidade === null ? '—' : 'R$ '.\App\Support\DinheiroBr::formatar($cobranca->valor_por_unidade) }}
                    </td>
                    <td class="text-sm">
                        {{ $cobranca->vigencia_inicio->format('d/m/Y') }}
                        — {{ $cobranca->vigencia_fim?->format('d/m/Y') ?? 'permanente' }}
                    </td>
                    <td>{{ $cobranca->ativa ? 'Sim' : 'Não' }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($cobranca->total_taxas ?? 0) }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($receitasPorCobranca[$cobranca->id] ?? 0) }}</td>
                    <td class="text-right">
                        <x-table-action wire:click="editar({{ $cobranca->id }})">Editar</x-table-action>
                        <x-table-action wire:click="abrirAplicacao({{ $cobranca->id }})">Aplicar</x-table-action>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="py-6 text-center text-slate-500">Nenhuma cobrança extraordinária cadastrada.</td>
                </tr>
            @endforelse
        </x-table>
    </div>
</div>
