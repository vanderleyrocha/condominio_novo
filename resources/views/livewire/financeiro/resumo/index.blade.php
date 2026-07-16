<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <h2 class="text-lg font-semibold text-gray-900">Resumo das receitas e despesas</h2>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Apartir de</label>
                    <input type="date" wire:model.live="apartirDe"
                           class="mt-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                </div>
                <a href="{{ route('pdf.resumo', array_filter(['apartir_de' => $apartirDe])) }}" target="_blank"
                   class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Download</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th rowspan="2" class="px-3 py-2 text-left">Ano</th>
                        <th colspan="{{ count($imoveis) + 2 }}" class="px-3 py-2 text-center">Receitas</th>
                        <th rowspan="2" class="px-3 py-2 text-right">Despesas</th>
                    </tr>
                    <tr>
                        @foreach ($imoveis as $imovel)
                            <th class="px-3 py-2 text-right">{{ $imovel }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-right">Outras</th>
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($resumo as $ano => $dados)
                        @php $totalAno = 0; @endphp
                        <tr>
                            <th class="px-3 py-2 text-left">{{ $ano }}</th>
                            @foreach ($imoveis as $imovel)
                                <td class="px-3 py-2 text-right">
                                    {{ (isset($dados[$imovel]) && $dados[$imovel] > 0) ? \App\Support\DinheiroBr::formatar($dados[$imovel]) : '-' }}
                                </td>
                                @php $totalAno += $dados[$imovel] ?? 0; @endphp
                            @endforeach
                            <td class="px-3 py-2 text-right">
                                {{ (isset($dados['receita']) && $dados['receita'] > 0) ? \App\Support\DinheiroBr::formatar($dados['receita']) : '-' }}
                            </td>
                            @php $totalAno += $dados['receita'] ?? 0; @endphp
                            <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($totalAno) }}</td>
                            <td class="px-3 py-2 text-right">
                                {{ (isset($dados['despesas']) && $dados['despesas'] > 0) ? \App\Support\DinheiroBr::formatar($dados['despesas']) : '-' }}
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-100">
                    <tr class="font-semibold">
                        <th class="px-3 py-2 text-left">Total</th>
                        @foreach ($imoveis as $imovel)
                            <th class="px-3 py-2 text-right">
                                {{ (isset($totalImovel[$imovel]) && $totalImovel[$imovel] > 0) ? \App\Support\DinheiroBr::formatar($totalImovel[$imovel]) : '-' }}
                            </th>
                        @endforeach
                        <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($totalReceita) }}</th>
                        <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($totalGeral + $totalReceita) }}</th>
                        <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($totalDespesa) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-6 rounded-md bg-gray-800 p-4 text-center text-white">
            <span class="text-2xl">Saldo:
                <span class="font-bold">
                    @if ($saldo != 0)
                        {{ \App\Support\DinheiroBr::formatar($saldo) }} + {{ \App\Support\DinheiroBr::formatar($totalGeral + $totalReceita - $totalDespesa) }} = {{ \App\Support\DinheiroBr::formatar($totalGeral + $totalReceita - $totalDespesa + $saldo) }}
                    @else
                        {{ \App\Support\DinheiroBr::formatar($totalGeral + $totalReceita - $totalDespesa + $saldo) }}
                    @endif
                </span>
            </span>
        </div>
    </div>

    @if ($cobrancas->isNotEmpty())
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Cobranças extras apuradas</h3>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-3 py-2">Cobrança</th>
                        <th class="px-3 py-2 text-right">Apurado em mensalidades</th>
                        <th class="px-3 py-2 text-right">Receitas vinculadas</th>
                        <th class="px-3 py-2 text-right">Total apurado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($cobrancas as $cobranca)
                        @php
                            $pivots = (float) ($cobranca->total_mensalidades ?? 0);
                            $vinculadas = (float) ($cobranca->total_receitas ?? 0);
                        @endphp
                        <tr>
                            <td class="px-3 py-2">{{ $cobranca->nome }}</td>
                            <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($pivots) }}</td>
                            <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($vinculadas) }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ \App\Support\DinheiroBr::formatar($pivots + $vinculadas) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
