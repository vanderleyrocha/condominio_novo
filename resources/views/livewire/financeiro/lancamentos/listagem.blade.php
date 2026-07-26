<div class="space-y-6">
    <div class="card">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Lançamentos Financeiros</h2>
            @can('create', \App\Models\LancamentoFinanceiro::class)
                <button type="button" wire:click="novoLancamento" class="btn btn-primary">Novo lançamento</button>
            @endcan
        </div>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        {{-- Filtros --}}
        <div class="mb-4 flex flex-wrap items-end gap-4">
            <div>
                <label class="label">Natureza</label>
                <select wire:model.live="natureza" class="input w-36">
                    <option value="">Todas</option>
                    <option value="receita">Receitas</option>
                    <option value="despesa">Despesas</option>
                </select>
            </div>
            <div>
                <label class="label">Ano</label>
                <input type="number" min="2000" max="2100" wire:model.live="ano" class="input w-28">
            </div>
            <div>
                <label class="label">Mês</label>
                <select wire:model.live="mes" class="input w-36">
                    <option value="">Todos</option>
                    @foreach (\App\Support\MesesBr::todos() as $numero => $nome)
                        <option value="{{ $numero }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Plano de contas</label>
                <select wire:model.live="plano" class="input w-56">
                    <option value="0">Todos</option>
                    @foreach ($planos as $planoConta)
                        <option value="{{ $planoConta->id }}">{{ $planoConta->codigo }} — {{ $planoConta->descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Descrição</label>
                <input type="text" wire:model.live.debounce.400ms="descricao" class="input w-56" placeholder="Buscar...">
            </div>
        </div>

        {{-- Formulário inline --}}
        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-4 text-base font-semibold text-slate-900">
                    {{ $lancamentoId === null ? 'Novo lançamento' : 'Editar lançamento' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="label">Natureza</label>
                        <select wire:model.live="formNatureza" class="input" @disabled($lancamentoId !== null)>
                            @foreach ($naturezas as $opcao)
                                <option value="{{ $opcao->value }}">{{ ucfirst($opcao->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Plano de contas</label>
                        <select wire:model="formPlanoId" class="input">
                            <option value="">Selecione...</option>
                            @foreach ($planosDoForm as $planoConta)
                                <option value="{{ $planoConta->id }}">{{ $planoConta->codigo }} — {{ $planoConta->descricao }}</option>
                            @endforeach
                        </select>
                        @error('formPlanoId') <p class="error-text text-xs">{{ $message }}</p> @enderror
                        <div class="mt-2 flex gap-2">
                            <input type="text" wire:model="novoPlano" placeholder="Criar novo plano..." class="input text-xs">
                            <button type="button" wire:click="criarPlano" class="btn btn-secondary btn-sm">Criar</button>
                        </div>
                        @error('novoPlano') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Data</label>
                        <input type="date" wire:model="formData" class="input">
                        @error('formData') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Descrição</label>
                        <input type="text" wire:model="formDescricao" class="input">
                        @error('formDescricao') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Valor (R$)</label>
                        <input type="text" wire:model="formValor" placeholder="0,00" class="input text-right">
                        @error('formValor') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    @if ($formNatureza === 'receita')
                        <div>
                            <label class="label">Cobrança extraordinária (origem)</label>
                            <select wire:model="formCobrancaId" class="input">
                                <option value="">Nenhuma</option>
                                @foreach ($cobrancas as $cobranca)
                                    <option value="{{ $cobranca->id }}">{{ $cobranca->nome }}</option>
                                @endforeach
                            </select>
                            @error('formCobrancaId') <p class="error-text text-xs">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="flex items-end pb-2">
                        <label class="label flex items-center gap-2">
                            <input type="checkbox" wire:model="formContabilizado" class="checkbox">
                            Contabilizado
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <button type="submit" class="btn btn-primary">Gravar</button>
                    <button type="button" wire:click="cancelar" class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        @endif

        <div class="-mx-6 overflow-x-auto px-6">
        <table class="table-modern">
            <thead>
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
            </thead>
            <tbody>
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
                                <button type="button" wire:click="editar({{ $lancamento->id }})" class="table-action">Editar</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-6 text-center text-slate-500">Nenhum lançamento encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <th colspan="6" class="text-left">
                        Totais do filtro — receitas: R$ {{ \App\Support\DinheiroBr::formatar($totalReceitas) }} /
                        despesas: R$ {{ \App\Support\DinheiroBr::formatar($totalDespesas) }}
                    </th>
                    <th class="text-right" colspan="2">
                        Saldo: R$ {{ \App\Support\DinheiroBr::formatar(bcsub($totalReceitas, $totalDespesas, 2)) }}
                    </th>
                </tr>
            </tfoot>
        </table>
        </div>
        <div class="mt-4">{{ $lancamentos->links() }}</div>
    </div>
</div>
