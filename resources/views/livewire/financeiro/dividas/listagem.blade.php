<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Dívidas</h2>
            <a href="{{ route('pdf.dividas.consolidado') }}" target="_blank"
               class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Download</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Imóvel</th>
                        @foreach ($anos as $ano)
                            <th class="px-3 py-2 text-right">{{ $ano }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($dividas as $nome => $porAno)
                        <tr>
                            <td class="px-3 py-2">{{ $loop->iteration }}</td>
                            <td class="px-3 py-2">
                                <a href="{{ route('dividas.imovel', $imoveis[$nome]) }}"
                                   class="rounded-md bg-brand px-3 py-1 text-xs font-medium text-white hover:bg-brand-dark">{{ $nome }}</a>
                            </td>
                            @foreach ($anos as $ano)
                                <td class="px-3 py-2 text-right">
                                    {{ isset($porAno[$ano]) ? \App\Support\DinheiroBr::formatar($porAno[$ano]) : '-' }}
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-semibold">
                                {{ \App\Support\DinheiroBr::formatar($totalImovel[$nome] ?? 0) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($anos) + 3 }}" class="px-3 py-6 text-center text-gray-500">Nenhuma dívida em aberto</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <th colspan="2" class="px-3 py-2 text-left">Total</th>
                        @foreach ($anos as $ano)
                            <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($totalAno[$ano] ?? 0) }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($totalGeral) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
