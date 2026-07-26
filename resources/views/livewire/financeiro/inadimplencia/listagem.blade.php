<div class="space-y-6">
    <div class="card">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Inadimplência</h2>
            <a href="{{ route('pdf-novo.inadimplencia.consolidado') }}" target="_blank"
               class="btn btn-primary">Download</a>
        </div>

        <div class="overflow-x-auto">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Unidade</th>
                        @foreach ($anos as $ano)
                            <th class="text-right">{{ $ano }}</th>
                        @endforeach
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dividas as $identificacao => $porAno)
                        <tr wire:key="inad-{{ $identificacao }}">
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <a href="{{ route('inadimplencia.unidade', $unidades[$identificacao]) }}"
                                   class="btn btn-primary btn-sm">{{ $identificacao }}</a>
                            </td>
                            @foreach ($anos as $ano)
                                <td class="text-right">
                                    {{ isset($porAno[$ano]) ? \App\Support\DinheiroBr::formatar($porAno[$ano]) : '-' }}
                                </td>
                            @endforeach
                            <td class="text-right font-semibold">
                                {{ \App\Support\DinheiroBr::formatar($totalUnidade[$identificacao] ?? 0) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($anos) + 3 }}" class="py-6 text-center text-slate-500">Nenhuma taxa em aberto</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <th colspan="2" class="text-left">Total</th>
                        @foreach ($anos as $ano)
                            <th class="text-right">{{ \App\Support\DinheiroBr::formatar($totalAno[$ano] ?? 0) }}</th>
                        @endforeach
                        <th class="text-right">{{ \App\Support\DinheiroBr::formatar($totalGeral) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
