<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Mensalidades</h1>
        <a href="{{ route('mensalidades.grade', $ano) }}"
           class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark">
            Grade anual
        </a>
    </div>

    {{-- Filtro --}}
    <div class="rounded-lg bg-white p-6 shadow">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="ano" class="mb-1 block text-sm font-medium text-gray-700">Ano</label>
                <input id="ano" type="number" min="2020" max="2030" wire:model.live="ano"
                       class="w-32 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
            </div>
            <div>
                <label for="imovelId" class="mb-1 block text-sm font-medium text-gray-700">Imóvel</label>
                <select id="imovelId" wire:model.live="imovelId"
                        class="w-72 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    <option value="">Selecione um imóvel</option>
                    @foreach ($imoveis as $imovel)
                        <option value="{{ $imovel->id }}">Ap {{ $imovel->nome }} - {{ $imovel->proprietario->nome ?? 'Não informado' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($imovelSelecionado !== null)
        {{-- Painel do imóvel --}}
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Informações do Imóvel</h2>
            <div class="flex flex-wrap gap-8 text-sm">
                <p><span class="font-medium text-gray-700">Imóvel:</span> {{ $imovelSelecionado->nome }}</p>
                <p><span class="font-medium text-gray-700">Proprietário:</span> {{ $imovelSelecionado->proprietario->nome ?? 'Não informado' }}</p>
            </div>
        </div>

        @if ($mensalidades !== null && $mensalidades->count() > 0)
            <div class="overflow-x-auto rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Mês</th>
                            <th class="px-4 py-3">Vencimento</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Acréscimo</th>
                            <th class="px-4 py-3">Desconto</th>
                            <th class="px-4 py-3">Valor Pago</th>
                            <th class="px-4 py-3">Data do Pagamento</th>
                            <th class="px-4 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($mensalidades as $mensalidade)
                            @php
                                $status = $mensalidade->status();
                                $corValorPago = match ($status) {
                                    \App\Enums\StatusMensalidade::Paga => 'text-green-600',
                                    \App\Enums\StatusMensalidade::PagaParcial => 'text-amber-600',
                                    default => 'text-red-600',
                                };
                            @endphp
                            <tr class="odd:bg-white even:bg-gray-50">
                                <td class="px-4 py-2">{{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</td>
                                <td class="px-4 py-2">{{ $mensalidade->vencimento?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-2">R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->valor) }}</td>
                                <td class="px-4 py-2">R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->acrescimo) }}</td>
                                <td class="px-4 py-2">R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->desconto) }}</td>
                                <td class="px-4 py-2 font-medium {{ $corValorPago }}">R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->valor_pago) }}</td>
                                <td class="px-4 py-2">{{ $mensalidade->pago_em?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex gap-2">
                                        @can('update', $mensalidade)
                                            <a href="{{ route('mensalidades.edit', $mensalidade) }}" title="Editar mensalidade"
                                               class="text-brand hover:underline">Editar</a>
                                        @endcan
                                        @if ((float) $mensalidade->valor_pago > 0)
                                            <a href="{{ route('pdf.mensalidades.recibo', $mensalidade) }}" target="_blank" title="Ver recibo"
                                               class="text-gray-600 hover:underline">Recibo</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-900 text-white">
                        <tr>
                            <td class="px-4 py-2 font-semibold" colspan="2">Totais</td>
                            <td class="px-4 py-2 font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalValor) }}</td>
                            <td class="px-4 py-2">-</td>
                            <td class="px-4 py-2">-</td>
                            <td class="px-4 py-2 font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalValorPago) }}</td>
                            <td class="px-4 py-2" colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div>{{ $mensalidades->links() }}</div>
        @else
            <div class="rounded-lg bg-white p-6 text-center shadow">
                <p class="mb-4 text-sm text-gray-600">Nenhuma mensalidade encontrada para este imóvel no ano de {{ $ano }}</p>
                @can('lancar', \App\Models\Mensalidade::class)
                    <a href="{{ route('mensalidades.lancar') }}"
                       class="inline-block rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark">
                        Lançar Mensalidades
                    </a>
                @endcan
            </div>
        @endif
    @else
        <div class="rounded-lg bg-white p-6 shadow">
            <p class="text-sm text-gray-600">Selecione um imóvel para visualizar as mensalidades</p>
        </div>
    @endif
</div>
