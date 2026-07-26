<div class="space-y-6">
    <h1 class="page-title">Relatório de Taxas Condominiais</h1>

    {{-- Filtros --}}
    <div class="card">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="unidadeId" class="label">Unidade</label>
                <select id="unidadeId" wire:model.live="unidadeId" class="input w-full sm:w-64">
                    <option value="">Todas</option>
                    @foreach ($unidades as $unidade)
                        <option value="{{ $unidade->id }}">{{ $unidade->identificacao }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ano" class="label">Ano</label>
                <input id="ano" type="number" min="2000" max="2100" wire:model.live="ano" class="input w-28">
            </div>
            <div>
                <label for="mes" class="label">Mês</label>
                <select id="mes" wire:model.live="mes" class="input w-full sm:w-40">
                    <option value="">Todos</option>
                    @foreach (\App\Support\MesesBr::todos() as $numero => $nome)
                        <option value="{{ $numero }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" wire:model.live="status" class="input w-full sm:w-44">
                    <option value="">Todos</option>
                    <option value="pago">Pago</option>
                    <option value="pago_parcial">Pago parcial</option>
                    <option value="vencida">Vencida</option>
                    <option value="aberto">Em aberto (em dia)</option>
                </select>
            </div>
        </div>
    </div>

    @if ($taxas->count() > 0)
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="table-modern">
                <thead>
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
                </thead>
                <tbody>
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
                                    <a href="{{ route('pdf-novo.taxas.recibo', $taxa) }}" target="_blank" title="Ver recibo"
                                       class="table-action">Recibo</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="font-semibold" colspan="4">Totais ({{ $quantidade }} taxas)</td>
                        <td></td>
                        <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalValor) }}</td>
                        <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalPago) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div>{{ $taxas->links() }}</div>
    @else
        <div class="card">
            <p class="text-sm text-slate-600">Nenhuma taxa encontrada para o período selecionado.</p>
        </div>
    @endif
</div>
