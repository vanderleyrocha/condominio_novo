<div class="space-y-6">
    <h1 class="text-xl font-semibold">Relatório de Mensalidades</h1>

    {{-- Filtros --}}
    <div class="rounded-lg bg-white p-6 shadow">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="imovelId" class="mb-1 block text-sm font-medium text-gray-700">Imóvel</label>
                <select id="imovelId" wire:model.live="imovelId"
                        class="w-64 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    <option value="">Todos</option>
                    @foreach ($imoveis as $imovel)
                        <option value="{{ $imovel->id }}">Ap {{ $imovel->nome }} - {{ $imovel->proprietario->nome ?? 'Não informado' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ano" class="mb-1 block text-sm font-medium text-gray-700">Ano</label>
                <input id="ano" type="number" min="2000" max="2100" wire:model.live="ano"
                       class="w-28 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
            </div>
            <div>
                <label for="mes" class="mb-1 block text-sm font-medium text-gray-700">Mês</label>
                <select id="mes" wire:model.live="mes"
                        class="w-40 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    <option value="">Todos</option>
                    @foreach (\App\Support\MesesBr::todos() as $numero => $nome)
                        <option value="{{ $numero }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select id="status" wire:model.live="status"
                        class="w-44 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
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
        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Imóvel</th>
                        <th class="px-4 py-3">Proprietário</th>
                        <th class="px-4 py-3">Ano</th>
                        <th class="px-4 py-3">Mês</th>
                        <th class="px-4 py-3">Vencimento</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Valor Pago</th>
                        <th class="px-4 py-3">Data Pagamento</th>
                        <th class="px-4 py-3">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($mensalidades as $mensalidade)
                        <tr class="odd:bg-white even:bg-gray-50">
                            <td class="px-4 py-2">{{ $mensalidade->imovel->nome ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $mensalidade->imovel->proprietario->nome ?? 'Não informado' }}</td>
                            <td class="px-4 py-2">{{ $mensalidade->ano }}</td>
                            <td class="px-4 py-2">{{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</td>
                            <td class="px-4 py-2">{{ $mensalidade->vencimento?->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-4 py-2">R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->valor) }}</td>
                            <td class="px-4 py-2">R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->valor_pago) }}</td>
                            <td class="px-4 py-2">{{ $mensalidade->pago_em?->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-4 py-2">
                                @if ((float) $mensalidade->valor_pago > 0)
                                    <a href="{{ route('pdf.mensalidades.recibo', $mensalidade) }}" target="_blank" title="Ver recibo"
                                       class="text-brand hover:underline">Recibo</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-900 text-white">
                    <tr>
                        <td class="px-4 py-2 font-semibold" colspan="4">Totais ({{ $totais->quantidade }} mensalidades)</td>
                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2 font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totais->total_valor) }}</td>
                        <td class="px-4 py-2 font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totais->total_pago) }}</td>
                        <td class="px-4 py-2" colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div>{{ $mensalidades->links() }}</div>
    @else
        <div class="rounded-lg bg-white p-6 shadow">
            <p class="text-sm text-gray-600">Nenhuma mensalidade encontrada para o período selecionado.</p>
        </div>
    @endif
</div>
