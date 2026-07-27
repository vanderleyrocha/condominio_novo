<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="page-title">Editar Taxa Condominial</h1>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    @if ($mensagemComposicao !== '')
        <div class="alert alert-success">{{ $mensagemComposicao }}</div>
    @endif

    <div class="card">
        <div class="mb-6 grid grid-cols-1 gap-4 border-b border-slate-100 pb-4 text-sm sm:grid-cols-3">
            <p><span class="font-medium text-slate-700">Unidade:</span> {{ $taxa->unidade->identificacao ?? '-' }}</p>
            <p><span class="font-medium text-slate-700">Competência:</span> {{ \App\Support\MesesBr::nome((int) $taxa->competencia_mes) }}/{{ $taxa->competencia_ano }}</p>
            <p><span class="font-medium text-slate-700">Status:</span> {{ $taxa->status->rotulo() }}</p>
        </div>

        {{-- Composição: o valor devido é a SOMA dos itens, não um campo editável --}}
        <div class="mb-6">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="card-title">Composição da mensalidade</h2>
                    <p class="text-xs text-slate-500">
                        O valor devido é a soma dos itens. Pagamentos parciais quitam os itens nesta ordem.
                    </p>
                </div>
                <x-button variant="secondary" wire:click="novoItem" type="button">Adicionar item</x-button>
            </div>

            <div class="overflow-x-auto">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Descrição</th>
                            <th>Finalidade</th>
                            <th class="text-right">Valor</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($itens as $item)
                            <tr>
                                <td class="tabular-nums text-slate-400">{{ $item->ordem }}</td>
                                <td>{{ $item->descricao }}</td>
                                <td class="text-slate-500">{{ $item->finalidade?->nome ?? '—' }}</td>
                                <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($item->valor) }}</td>
                                <td class="text-right">
                                    <x-table-action wire:click="editarItem({{ $item->id }})">Editar</x-table-action>
                                    @if ($podeRemoverItem)
                                        <x-table-action wire:click="removerItem({{ $item->id }})"
                                                        wire:confirm="Remover “{{ $item->descricao }}” desta competência?">
                                            Remover
                                        </x-table-action>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-slate-500">
                                    Esta taxa ainda não foi decomposta em itens.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="3">Valor devido</td>
                            <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_original) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($itemAberto)
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-slate-700">
                        {{ $itemId === null ? 'Novo item' : 'Editar item' }}
                    </h3>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <x-input label="Descrição" wire:model="itemDescricao" placeholder="Ex.: Taxa para pintura do prédio" />

                        <x-input label="Valor" wire:model="itemValor" inputmode="decimal" placeholder="0,00" />

                        <x-select label="Plano de contas" wire:model="itemPlanoContaId">
                            @foreach ($planos as $id => $rotulo)
                                <option value="{{ $id }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>

                        <x-select label="Finalidade" wire:model="itemFinalidadeId"
                                  help="Para que serve a arrecadação deste item.">
                            <option value="">— sem finalidade específica —</option>
                            @foreach ($finalidades as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </x-select>

                        <div class="flex items-center gap-3 sm:col-span-2">
                            <x-button wire:click="salvarItem" type="button">Salvar item</x-button>
                            <button type="button" wire:click="cancelarItem"
                                    class="text-sm text-slate-500 hover:underline">Cancelar</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <form wire:submit="salvar" class="grid grid-cols-1 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
            <x-input label="Data de vencimento" type="date" wire:model="vencimento" />

            <div class="hidden sm:block"></div>

            <x-input label="Acréscimo" wire:model="acrescimo" inputmode="decimal" placeholder="0,00" />

            <x-input label="Desconto" wire:model="desconto" inputmode="decimal" placeholder="0,00" />

            <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                <span class="font-medium text-slate-700">Valor pago (derivado dos pagamentos):</span>
                R$ {{ \App\Support\DinheiroBr::formatar($valorPago) }}
                <p class="mt-1 text-xs text-slate-500">
                    Pagamentos são registrados no módulo de pagamentos ou pela grade anual —
                    o status desta taxa é recalculado automaticamente.
                </p>
            </div>

            <div class="sm:col-span-2">
                @can('gerenciarContabilizado', \App\Models\TaxaCondominial::class)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="contabilizado" class="checkbox">
                        Contabilizada
                    </label>
                @else
                    <label class="flex items-center gap-2 text-sm text-slate-400">
                        <input type="checkbox" checked disabled class="checkbox">
                        Contabilizada
                    </label>
                @endcan
            </div>

            <div class="flex items-center gap-3 sm:col-span-2">
                <x-button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Salvar</span>
                    <span wire:loading>Salvando...</span>
                </x-button>
                @if ((float) $valorPago > 0)
                    <x-button variant="secondary" :href="route('pdf.taxas.recibo', $taxa)" target="_blank">Recibo</x-button>
                @endif
                <a href="{{ route('taxas.index', ['ano' => $taxa->competencia_ano, 'unidade' => $taxa->unidade_id]) }}"
                   class="text-sm text-slate-500 hover:underline">Voltar</a>
            </div>
        </form>
    </div>
</div>
