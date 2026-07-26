<div class="card">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Unidades Cadastradas</h1>
        @if (! $exibirFormulario)
            <button type="button" wire:click="novo" class="btn btn-primary">
                Nova Unidade
            </button>
        @endif
    </div>

    {{-- Formulário inline (padrão da tela de imóveis) --}}
    @if ($exibirFormulario)
        <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <h2 class="section-label mb-4">{{ $unidadeId ? 'Editar Unidade' : 'Nova Unidade' }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="identificacao" class="label">Identificação *</label>
                    <input id="identificacao" type="text" wire:model="identificacao" class="input" placeholder="Ex.: Casa 01">
                    @error('identificacao') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="fracao_ideal" class="label">Fração ideal</label>
                    <input id="fracao_ideal" type="number" step="0.000001" min="0" max="1" wire:model="fracao_ideal"
                           class="input" placeholder="Ex.: 0.062500">
                    @error('fracao_ideal') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="area" class="label">Área (m²)</label>
                    <input id="area" type="number" step="0.01" min="0" wire:model="area" class="input">
                    @error('area') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="vagas_garagem" class="label">Vagas de garagem</label>
                    <input id="vagas_garagem" type="number" min="0" max="20" wire:model="vagas_garagem" class="input">
                    @error('vagas_garagem') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="cancelar" class="btn btn-secondary">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">
                    {{ $unidadeId ? 'Atualizar' : 'Salvar' }}
                </button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Identificação</th>
                    <th>Vínculos vigentes</th>
                    <th>Responsável financeiro</th>
                    <th class="text-center">Taxas</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($unidades as $unidade)
                    <tr wire:key="unidade-{{ $unidade->id }}">
                        <td>{{ $unidade->identificacao }}</td>
                        <td>
                            @forelse ($unidade->vinculos as $vinculo)
                                <span class="block text-sm">
                                    {{ $vinculo->pessoa->nome }}
                                    <span class="text-xs text-slate-500">({{ $vinculo->papel->rotulo() }})</span>
                                </span>
                            @empty
                                <span class="text-slate-400">—</span>
                            @endforelse
                        </td>
                        <td>
                            {{ $unidade->vinculos->firstWhere('responsavel_financeiro', true)?->pessoa->nome ?? '—' }}
                        </td>
                        <td class="text-center">{{ $unidade->taxas_condominiais_count }}</td>
                        <td class="text-right">
                            <button type="button" wire:click="abrirVinculos({{ $unidade->id }})" title="Gerir vínculos"
                                    class="table-action">
                                Vínculos
                            </button>
                            <button type="button" wire:click="editar({{ $unidade->id }})" title="Editar"
                                    class="table-action">
                                Editar
                            </button>
                            <button type="button" wire:click="confirmarExclusao({{ $unidade->id }})" title="Excluir"
                                    class="table-action-danger">
                                Excluir
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-slate-500">Nenhuma unidade encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal de confirmação de exclusão (DEV-13) --}}
    @if ($confirmandoExclusaoId !== null)
        <div class="modal-overlay" wire:keydown.escape.window="cancelarExclusao">
            <div class="modal-panel">
                <p class="mb-6 text-sm text-slate-700">Tem certeza que deseja excluir esta unidade?</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelarExclusao" class="btn btn-secondary">Cancelar</button>
                    <button type="button" wire:click="excluir" wire:loading.attr="disabled" class="btn btn-danger">Excluir</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de vínculos pessoa↔unidade --}}
    @if ($unidadeVinculos !== null)
        <div class="modal-overlay" wire:keydown.escape.window="fecharVinculos">
            <div class="modal-panel max-w-3xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="page-title text-lg">Vínculos — {{ $unidadeVinculos->identificacao }}</h2>
                    <button type="button" wire:click="fecharVinculos" class="btn btn-secondary">Fechar</button>
                </div>

                <table class="table-modern mb-6">
                    <thead>
                        <tr>
                            <th>Pessoa</th>
                            <th>Papel</th>
                            <th>Responsável</th>
                            <th>Vigência</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unidadeVinculos->vinculos as $vinculo)
                            <tr wire:key="vinculo-{{ $vinculo->id }}" @class(['opacity-60' => $vinculo->data_fim !== null])>
                                <td>{{ $vinculo->pessoa->nome }}</td>
                                <td>{{ $vinculo->papel->rotulo() }}</td>
                                <td>{{ $vinculo->responsavel_financeiro ? 'Sim' : '—' }}</td>
                                <td class="text-sm">
                                    {{ $vinculo->data_inicio->format('d/m/Y') }}
                                    — {{ $vinculo->data_fim?->format('d/m/Y') ?? 'vigente' }}
                                </td>
                                <td class="text-right">
                                    @if ($vinculo->data_fim === null)
                                        <button type="button" wire:click="encerrarVinculo({{ $vinculo->id }})"
                                                wire:confirm="Encerrar o vínculo de {{ $vinculo->pessoa->nome }}?"
                                                class="table-action-danger">
                                            Encerrar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-slate-500">Nenhum vínculo registrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <form wire:submit="vincular" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="section-label mb-3">Adicionar vínculo</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <label for="vinculoPessoaId" class="label">Pessoa *</label>
                            <select id="vinculoPessoaId" wire:model="vinculoPessoaId" class="input">
                                <option value="">Selecione...</option>
                                @foreach ($pessoas as $pessoa)
                                    <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                                @endforeach
                            </select>
                            @error('vinculoPessoaId') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="vinculoPapel" class="label">Papel *</label>
                            <select id="vinculoPapel" wire:model="vinculoPapel" class="input">
                                @foreach ($papeis as $papel)
                                    <option value="{{ $papel->value }}">{{ $papel->rotulo() }}</option>
                                @endforeach
                            </select>
                            @error('vinculoPapel') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="vinculoDataInicio" class="label">Início *</label>
                            <input id="vinculoDataInicio" type="date" wire:model="vinculoDataInicio" class="input">
                            @error('vinculoDataInicio') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="label flex items-center gap-2">
                                <input type="checkbox" wire:model="vinculoResponsavel">
                                Responsável financeiro
                            </label>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Marcar "Responsável financeiro" transfere a responsabilidade do responsável vigente, se houver.
                    </p>
                    <div class="mt-3 flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">Vincular</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
