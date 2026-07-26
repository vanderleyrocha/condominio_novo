<div class="card">
    <x-page-header title="Unidades Cadastradas" class="mb-4">
        @if (! $exibirFormulario)
            <x-button wire:click="novo">Nova Unidade</x-button>
        @endif
    </x-page-header>

    {{-- Formulário inline (padrão da tela de imóveis) --}}
    @if ($exibirFormulario)
        <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <h2 class="section-label mb-4">{{ $unidadeId ? 'Editar Unidade' : 'Nova Unidade' }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <x-input label="Identificação *" wire:model="identificacao" placeholder="Ex.: Casa 01" />
                <x-input label="Fração ideal" type="number" step="0.000001" min="0" max="1"
                         wire:model="fracao_ideal" placeholder="Ex.: 0.062500" />
                <x-input label="Área (m²)" type="number" step="0.01" min="0" wire:model="area" />
                <x-input label="Vagas de garagem" type="number" min="0" max="20" wire:model="vagas_garagem" />
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <x-button variant="secondary" wire:click="cancelar">Cancelar</x-button>
                <x-button type="submit" wire:loading.attr="disabled">
                    {{ $unidadeId ? 'Atualizar' : 'Salvar' }}
                </x-button>
            </div>
        </form>
    @endif

    <x-table>
        <x-slot:head>
            <tr>
                <th>Identificação</th>
                <th>Vínculos vigentes</th>
                <th>Responsável financeiro</th>
                <th class="text-center">Taxas</th>
                <th class="text-right">Ações</th>
            </tr>
        </x-slot:head>
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
                    <div class="flex justify-end gap-2">
                        <x-table-action wire:click="abrirVinculos({{ $unidade->id }})" title="Gerir vínculos">
                            Vínculos
                        </x-table-action>
                        <x-table-action wire:click="editar({{ $unidade->id }})" title="Editar">
                            Editar
                        </x-table-action>
                        <x-table-action variant="danger" wire:click="confirmarExclusao({{ $unidade->id }})" title="Excluir">
                            Excluir
                        </x-table-action>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="py-6 text-center text-slate-500">Nenhuma unidade encontrada.</td>
            </tr>
        @endforelse
    </x-table>

    {{-- Modal de confirmação de exclusão (DEV-13) --}}
    @if ($confirmandoExclusaoId !== null)
        <x-modal close="cancelarExclusao">
            <p class="text-sm text-slate-700">Tem certeza que deseja excluir esta unidade?</p>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelarExclusao">Cancelar</x-button>
                <x-button variant="danger" wire:click="excluir" wire:loading.attr="disabled">Excluir</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    {{-- Modal de vínculos pessoa↔unidade --}}
    @if ($unidadeVinculos !== null)
        <x-modal close="fecharVinculos" maxWidth="3xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="page-title text-lg">Vínculos — {{ $unidadeVinculos->identificacao }}</h2>
                <x-button variant="secondary" wire:click="fecharVinculos">Fechar</x-button>
            </div>

            <x-table class="mb-6">
                <x-slot:head>
                    <tr>
                        <th>Pessoa</th>
                        <th>Papel</th>
                        <th>Responsável</th>
                        <th>Vigência</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </x-slot:head>
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
                                <x-table-action variant="danger" wire:click="encerrarVinculo({{ $vinculo->id }})"
                                                wire:confirm="Encerrar o vínculo de {{ $vinculo->pessoa->nome }}?">
                                    Encerrar
                                </x-table-action>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center text-slate-500">Nenhum vínculo registrado.</td>
                    </tr>
                @endforelse
            </x-table>

            <form wire:submit="vincular" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <h3 class="section-label mb-3">Adicionar vínculo</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <x-select label="Pessoa *" wire:model="vinculoPessoaId">
                        <option value="">Selecione...</option>
                        @foreach ($pessoas as $pessoa)
                            <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Papel *" wire:model="vinculoPapel">
                        @foreach ($papeis as $papel)
                            <option value="{{ $papel->value }}">{{ $papel->rotulo() }}</option>
                        @endforeach
                    </x-select>
                    <x-input label="Início *" type="date" wire:model="vinculoDataInicio" />
                    <div class="flex items-end pb-2">
                        <label class="label flex items-center gap-2">
                            <input type="checkbox" wire:model="vinculoResponsavel" class="checkbox">
                            Responsável financeiro
                        </label>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    Marcar "Responsável financeiro" transfere a responsabilidade do responsável vigente, se houver.
                </p>
                <div class="mt-3 flex justify-end">
                    <x-button type="submit" wire:loading.attr="disabled">Vincular</x-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
