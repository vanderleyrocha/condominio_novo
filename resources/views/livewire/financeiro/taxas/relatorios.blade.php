<div class="space-y-6">
    <h1 class="page-title">Relatório de Taxas Condominiais</h1>

    {{-- Filtros --}}
    <div class="card">
        <div class="flex flex-wrap items-end gap-4">
            <x-select label="Unidade" wire:model.live="unidadeId" class="w-full sm:w-64">
                <option value="">Todas</option>
                @foreach ($unidades as $unidade)
                    <option value="{{ $unidade->id }}">{{ $unidade->identificacao }}</option>
                @endforeach
            </x-select>
            <x-input label="Ano" type="number" min="2000" max="2100" wire:model.live="ano" class="w-28" />
            <x-select label="Mês" wire:model.live="mes" class="w-full sm:w-40">
                <option value="">Todos</option>
                @foreach (\App\Support\MesesBr::todos() as $numero => $nome)
                    <option value="{{ $numero }}">{{ $nome }}</option>
                @endforeach
            </x-select>
            <x-select label="Status" wire:model.live="status" class="w-full sm:w-44">
                <option value="">Todos</option>
                <option value="pago">Pago</option>
                <option value="pago_parcial">Pago parcial</option>
                <option value="vencida">Vencida</option>
                <option value="aberto">Em aberto (em dia)</option>
            </x-select>
        </div>
    </div>

    @if ($taxas->count() > 0)
        <x-table class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <x-slot:head>
                <tr>
                    <th>Unidade</th>
                    <th>Responsável</th>
                    <th>Ano</th>
                    <th>Mês</th>
                    <th>Vencimento</th>
                    <th>Valor</th>
                    <th>Valor Pago</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </x-slot:head>
            @foreach ($taxas as $taxa)
                <tr wire:key="rel-taxa-{{ $taxa->id }}">
                    <td>{{ $taxa->unidade->identificacao ?? '-' }}</td>
                    <td>{{ $taxa->unidade->vinculos->first()?->pessoa->nome ?? 'Não informado' }}</td>
                    <td>{{ $taxa->competencia_ano }}</td>
                    <td>{{ \App\Support\MesesBr::nome((int) $taxa->competencia_mes) }}</td>
                    <td>{{ $taxa->vencimento?->format('d/m/Y') ?? '-' }}</td>
                    <td>R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_original) }}</td>
                    <td>R$ {{ \App\Support\DinheiroBr::formatar($taxa->valor_pago ?? '0.00') }}</td>
                    <td>{{ $taxa->status->rotulo() }}{{ $taxa->vencida() ? ' (vencida)' : '' }}</td>
                    <td>
                        @if ((float) ($taxa->valor_pago ?? 0) > 0)
                            <x-table-action :href="route('pdf.taxas.recibo', $taxa)" target="_blank" title="Ver recibo">
                                Recibo
                            </x-table-action>
                        @endif
                    </td>
                </tr>
            @endforeach
            <x-slot:foot>
                <tr>
                    <td class="font-semibold" colspan="4">Totais ({{ $quantidade }} taxas)</td>
                    <td></td>
                    <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalValor) }}</td>
                    <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalPago) }}</td>
                    <td colspan="2"></td>
                </tr>
            </x-slot:foot>
        </x-table>
        <div>{{ $taxas->links() }}</div>
    @else
        <div class="card">
            <p class="text-sm text-slate-600">Nenhuma taxa encontrada para o período selecionado.</p>
        </div>
    @endif
</div>
