<div class="space-y-6">
    <div class="card">
        <x-page-header title="Lançamentos Financeiros" class="mb-4">
            @can('create', \App\Models\LancamentoFinanceiro::class)
                <x-button wire:click="novoLancamento">Novo lançamento</x-button>
            @endcan
        </x-page-header>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        {{-- Filtros --}}
        <div class="mb-4 flex flex-wrap items-end gap-4">
            <x-select label="Natureza" wire:model.live="natureza" class="w-36">
                <option value="">Todas</option>
                <option value="receita">Receitas</option>
                <option value="despesa">Despesas</option>
            </x-select>
            <x-input label="Ano" type="number" min="2000" max="2100" wire:model.live="ano" class="w-28" />
            <x-select label="Mês" wire:model.live="mes" class="w-36">
                <option value="">Todos</option>
                @foreach (\App\Support\MesesBr::todos() as $numero => $nome)
                    <option value="{{ $numero }}">{{ $nome }}</option>
                @endforeach
            </x-select>
            <x-select label="Plano de contas" wire:model.live="plano" class="w-56">
                <option value="0">Todos</option>
                @foreach ($planos as $planoConta)
                    <option value="{{ $planoConta->id }}">{{ $planoConta->codigo }} — {{ $planoConta->descricao }}</option>
                @endforeach
            </x-select>
            <x-input label="Descrição" wire:model.live.debounce.400ms="descricao" class="w-56" placeholder="Buscar..." />
        </div>

        {{-- Formulário inline --}}
        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-4 text-base font-semibold text-slate-900">
                    {{ $lancamentoId === null ? 'Novo lançamento' : 'Editar lançamento' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <x-select label="Natureza" wire:model.live="formNatureza" :disabled="$lancamentoId !== null">
                        @foreach ($naturezas as $opcao)
                            <option value="{{ $opcao->value }}">{{ ucfirst($opcao->value) }}</option>
                        @endforeach
                    </x-select>
                    <div>
                        <x-select label="Plano de contas" wire:model="formPlanoId">
                            <option value="">Selecione...</option>
                            @foreach ($planosDoForm as $planoConta)
                                <option value="{{ $planoConta->id }}">{{ $planoConta->codigo }} — {{ $planoConta->descricao }}</option>
                            @endforeach
                        </x-select>
                        <div class="mt-2 flex gap-2">
                            <x-input wire:model="novoPlano" placeholder="Criar novo plano..." class="text-xs" />
                            <x-button variant="secondary" size="sm" wire:click="criarPlano">Criar</x-button>
                        </div>
                    </div>
                    <x-input label="Data" type="date" wire:model="formData" />
                    <x-input label="Descrição" wire:model="formDescricao" />
                    <x-input label="Valor (R$)" wire:model="formValor" placeholder="0,00" class="text-right" />
                    @if ($formNatureza === 'receita')
                        <x-select label="Cobrança extraordinária (origem)" wire:model="formCobrancaId">
                            <option value="">Nenhuma</option>
                            @foreach ($cobrancas as $cobranca)
                                <option value="{{ $cobranca->id }}">{{ $cobranca->nome }}</option>
                            @endforeach
                        </x-select>
                    @endif
                    <div class="flex items-end pb-2">
                        <label class="label flex items-center gap-2">
                            <input type="checkbox" wire:model="formContabilizado" class="checkbox">
                            Contabilizado
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
                    <th>Data</th>
                    <th>Natureza</th>
                    <th>Plano</th>
                    <th>Descrição</th>
                    <th>Origem</th>
                    <th>Contab.</th>
                    <th class="text-right">Valor</th>
                    <th class="text-right">Ações</th>
                </tr>
            </x-slot:head>
            @forelse ($lancamentos as $lancamento)
                <tr wire:key="lanc-{{ $lancamento->id }}">
                    <td>{{ $lancamento->data_lancamento?->format('d/m/Y') ?? '-' }}</td>
                    <td>
                        <span @class([
                            'text-emerald-600' => $lancamento->natureza === \App\Enums\NaturezaLancamento::Receita,
                            'text-red-600' => $lancamento->natureza === \App\Enums\NaturezaLancamento::Despesa,
                        ])>{{ ucfirst($lancamento->natureza->value) }}</span>
                    </td>
                    <td class="text-sm">{{ $lancamento->planoConta->descricao ?? '-' }}</td>
                    <td>{{ $lancamento->descricao }}</td>
                    <td class="text-sm">{{ $lancamento->origem?->nome ?? '-' }}</td>
                    <td>{{ $lancamento->contabilizado ? 'Sim' : 'Não' }}</td>
                    <td class="text-right font-medium">R$ {{ \App\Support\DinheiroBr::formatar($lancamento->valor) }}</td>
                    <td class="text-right">
                        @can('update', $lancamento)
                            <x-table-action wire:click="editar({{ $lancamento->id }})">Editar</x-table-action>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="py-6 text-center text-slate-500">Nenhum lançamento encontrado.</td>
                </tr>
            @endforelse
            <x-slot:foot>
                <tr class="font-semibold">
                    <th colspan="6" class="text-left">
                        Totais do filtro — receitas: R$ {{ \App\Support\DinheiroBr::formatar($totalReceitas) }} /
                        despesas: R$ {{ \App\Support\DinheiroBr::formatar($totalDespesas) }}
                    </th>
                    <th class="text-right" colspan="2">
                        Saldo: R$ {{ \App\Support\DinheiroBr::formatar(bcsub($totalReceitas, $totalDespesas, 2)) }}
                    </th>
                </tr>
            </x-slot:foot>
        </x-table>
        <div class="mt-4">{{ $lancamentos->links() }}</div>
    </div>
</div>
