<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Taxas Condominiais</h1>
        <a href="{{ route('taxas.grade', $ano) }}" class="btn btn-primary">
            Grade anual
        </a>
    </div>

    {{-- Filtro --}}
    <div class="card">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="ano" class="label">Ano</label>
                <input id="ano" type="number" min="2000" max="2100" wire:model.live="ano" class="input w-32">
            </div>
            <div>
                <label for="unidadeId" class="label">Unidade</label>
                <select id="unidadeId" wire:model.live="unidadeId" class="input w-full sm:w-72">
                    <option value="">Selecione uma unidade</option>
                    @foreach ($unidades as $unidade)
                        <option value="{{ $unidade->id }}">
                            {{ $unidade->identificacao }} - {{ $unidade->vinculos->first()?->pessoa->nome ?? 'Sem vínculo' }}
                        </option>
                    @endforeach
                </select>
            </div>
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
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="table-modern">
                    <thead>
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
                    </thead>
                    <tbody>
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
                                <td>R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_original) }}</td>
                                <td>R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_acrescimo) }}</td>
                                <td>R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_desconto) }}</td>
                                <td class="font-medium {{ $corValorPago }}">R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_pago ?? '0.00') }}</td>
                                <td>{{ $taxa->ultimo_pagamento !== null ? \Carbon\Carbon::parse($taxa->ultimo_pagamento)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $taxa->status->rotulo() }}{{ $taxa->vencida() ? ' (vencida)' : '' }}</td>
                                <td>
                                    <div class="flex gap-2">
                                        @can('update', $taxa)
                                            <a href="{{ route('taxas.edit', $taxa) }}" title="Editar taxa"
                                               class="table-action">Editar</a>
                                        @endcan
                                        @if ((float) ($taxa->valor_pago ?? 0) > 0)
                                            <a href="{{ route('pdf.taxas.recibo', $taxa) }}" target="_blank" title="Ver recibo"
                                               class="table-action-muted">Recibo</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="font-semibold" colspan="2">Totais</td>
                            <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalValor) }}</td>
                            <td>-</td>
                            <td>-</td>
                            <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalPago) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div>{{ $taxas->links() }}</div>
        @else
            <div class="card py-10 text-center">
                <p class="mb-4 text-sm text-slate-600">Nenhuma taxa encontrada para esta unidade no ano de {{ $ano }}</p>
                @can('lancar', \App\Models\TaxaCondominial::class)
                    <a href="{{ route('taxas.lancar') }}" class="btn btn-primary">
                        Lançar Taxas
                    </a>
                @endcan
            </div>
        @endif
    @else
        <div class="card">
            <p class="text-sm text-slate-600">Selecione uma unidade para visualizar as taxas</p>
        </div>
    @endif
</div>
