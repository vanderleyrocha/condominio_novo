<div class="space-y-6">
    <h1 class="page-title">Relatório de Mensalidades</h1>

    {{-- Filtros --}}
    <div class="card">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="imovelId" class="label">Imóvel</label>
                <select id="imovelId" wire:model.live="imovelId"
                        class="input w-full sm:w-64">
                    <option value="">Todos</option>
                    @foreach ($imoveis as $imovel)
                        <option value="{{ $imovel->id }}">Ap {{ $imovel->nome }} - {{ $imovel->proprietario->nome ?? 'Não informado' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ano" class="label">Ano</label>
                <input id="ano" type="number" min="2000" max="2100" wire:model.live="ano"
                       class="input w-28">
            </div>
            <div>
                <label for="mes" class="label">Mês</label>
                <select id="mes" wire:model.live="mes"
                        class="input w-full sm:w-40">
                    <option value="">Todos</option>
                    @foreach (\App\Support\MesesBr::todos() as $numero => $nome)
                        <option value="{{ $numero }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" wire:model.live="status"
                        class="input w-full sm:w-44">
                    <option value="">Todos</option>
                    <option value="paga">Paga</option>
                    <option value="paga_parcial">Paga parcialmente</option>
                    <option value="vencida">Vencida</option>
                    <option value="em_aberto">Em aberto</option>
                </select>
            </div>
        </div>
    </div>

    @if ($mensalidades->count() > 0)
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Imóvel</th>
                        <th>Proprietário</th>
                        <th>Ano</th>
                        <th>Mês</th>
                        <th>Vencimento</th>
                        <th>Valor</th>
                        <th>Valor Pago</th>
                        <th>Data Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mensalidades as $mensalidade)
                        <tr>
                            <td>{{ $mensalidade->imovel->nome ?? '-' }}</td>
                            <td>{{ $mensalidade->imovel->proprietario->nome ?? 'Não informado' }}</td>
                            <td>{{ $mensalidade->ano }}</td>
                            <td>{{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</td>
                            <td>{{ $mensalidade->vencimento?->format('d/m/Y') ?? '-' }}</td>
                            <td>R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->valor) }}</td>
                            <td>R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->valor_pago) }}</td>
                            <td>{{ $mensalidade->pago_em?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                @if ((float) $mensalidade->valor_pago > 0)
                                    <a href="{{ route('pdf.mensalidades.recibo', $mensalidade) }}" target="_blank" title="Ver recibo"
                                       class="table-action">Recibo</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="font-semibold" colspan="4">Totais ({{ $totais->quantidade }} mensalidades)</td>
                        <td></td>
                        <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totais->total_valor) }}</td>
                        <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totais->total_pago) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div>{{ $mensalidades->links() }}</div>
    @else
        <div class="card">
            <p class="text-sm text-slate-600">Nenhuma mensalidade encontrada para o período selecionado.</p>
        </div>
    @endif
</div>
