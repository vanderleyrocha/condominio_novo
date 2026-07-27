<div class="space-y-6">
    <x-page-header title="Taxas Condominiais">
        <x-button :href="route('taxas.grade', $ano)">Grade anual</x-button>
    </x-page-header>

    {{-- Filtro --}}
    <div class="card">
        <div class="flex flex-wrap items-end gap-4">
            <x-input label="Ano" type="number" min="2000" max="2100" wire:model.live="ano" class="w-32" />
            <x-select label="Unidade" wire:model.live="unidadeId" class="w-full sm:w-72">
                <option value="">Selecione uma unidade</option>
                @foreach ($unidades as $unidade)
                    <option value="{{ $unidade->id }}">
                        {{ $unidade->identificacao }} - {{ $unidade->vinculos->first()?->pessoa->nome ?? 'Sem vínculo' }}
                    </option>
                @endforeach
            </x-select>
        </div>
    </div>

    @if ($unidadeSelecionada !== null)
        <div class="card">
            <h2 class="section-label mb-3">Informações da Unidade</h2>
            <div class="flex flex-wrap gap-8 text-sm">
                <p><span class="font-medium text-slate-700">Unidade:</span> {{ $unidadeSelecionada->identificacao }}</p>
                <p>
                    <span class="font-medium text-slate-700">Responsável financeiro:</span>
                    {{ $unidadeSelecionada->vinculos->firstWhere('responsavel_financeiro', true)?->pessoa->nome ?? 'Não informado' }}
                </p>
            </div>
        </div>

        @if ($taxas !== null && $taxas->count() > 0)
            <x-table class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <x-slot:head>
                    <tr>
                        <th>Mês</th>
                        <th>Vencimento</th>
                        <th>Valor</th>
                        <th>Acréscimo</th>
                        <th>Desconto</th>
                        <th>Valor Pago</th>
                        <th>Último Pagamento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </x-slot:head>
                @foreach ($taxas as $taxa)
                    @php
                        $corValorPago = match ($taxa->status) {
                            \App\Enums\StatusTaxa::Pago => 'text-emerald-600',
                            \App\Enums\StatusTaxa::PagoParcial => 'text-amber-600',
                            default => 'text-red-600',
                        };
                    @endphp
                    <tr wire:key="taxa-{{ $taxa->id }}">
                        <td>{{ \App\Support\MesesBr::nome((int) $taxa->competencia_mes) }}</td>
                        <td>{{ $taxa->vencimento?->format('d/m/Y') ?? '-' }}</td>
                        <td>
                            R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_original) }}
                            {{-- Discriminação da composição: só faz sentido com mais de um item --}}
                            @if ($taxa->itens->count() > 1)
                                <p class="text-xs text-slate-500">
                                    @foreach ($taxa->itens as $item)
                                        <span title="{{ $item->finalidade?->nome ?? 'Sem finalidade específica' }}">{{ $item->descricao }}: {{ \App\Support\DinheiroBr::formatar($item->valor) }}</span>@if (! $loop->last)<br>@endif
                                    @endforeach
                                </p>
                            @endif
                        </td>
                        <td>R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_acrescimo) }}</td>
                        <td>R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_desconto) }}</td>
                        <td class="font-medium {{ $corValorPago }}">R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_pago ?? '0.00') }}</td>
                        <td>{{ $taxa->ultimo_pagamento !== null ? \Carbon\Carbon::parse($taxa->ultimo_pagamento)->format('d/m/Y') : '-' }}</td>
                        <td>{{ $taxa->status->rotulo() }}{{ $taxa->vencida() ? ' (vencida)' : '' }}</td>
                        <td>
                            <div class="flex gap-2">
                                @can('update', $taxa)
                                    <x-table-action :href="route('taxas.edit', $taxa)" title="Editar taxa">Editar</x-table-action>
                                @endcan
                                @if ((float) ($taxa->valor_pago ?? 0) > 0)
                                    <x-table-action variant="muted" :href="route('pdf.taxas.recibo', $taxa)" target="_blank" title="Ver recibo">
                                        Recibo
                                    </x-table-action>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:foot>
                    <tr>
                        <td class="font-semibold" colspan="2">Totais</td>
                        <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalValor) }}</td>
                        <td>-</td>
                        <td>-</td>
                        <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalPago) }}</td>
                        <td colspan="3"></td>
                    </tr>
                </x-slot:foot>
            </x-table>
            <div>{{ $taxas->links() }}</div>
        @else
            <x-empty-state message="Nenhuma taxa encontrada para esta unidade no ano de {{ $ano }}">
                @can('lancar', \App\Models\TaxaCondominial::class)
                    <x-button :href="route('taxas.lancar')">Lançar Taxas</x-button>
                @endcan
            </x-empty-state>
        @endif
    @else
        <div class="card">
            <p class="text-sm text-slate-600">Selecione uma unidade para visualizar as taxas</p>
        </div>
    @endif
</div>
