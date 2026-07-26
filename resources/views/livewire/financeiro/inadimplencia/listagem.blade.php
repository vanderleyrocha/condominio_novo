<div class="space-y-6">
    <div class="card">
        <x-page-header title="Inadimplência" class="mb-4">
            <x-button :href="route('pdf.inadimplencia.consolidado')" target="_blank">Download</x-button>
        </x-page-header>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>#</th>
                    <th>Unidade</th>
                    @foreach ($anos as $ano)
                        <th class="text-right">{{ $ano }}</th>
                    @endforeach
                    <th class="text-right">Total</th>
                </tr>
            </x-slot:head>
            @forelse ($dividas as $identificacao => $porAno)
                <tr wire:key="inad-{{ $identificacao }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <x-button size="sm" :href="route('inadimplencia.unidade', $unidades[$identificacao])">
                            {{ $identificacao }}
                        </x-button>
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
            <x-slot:foot>
                <tr class="font-semibold">
                    <th colspan="2" class="text-left">Total</th>
                    @foreach ($anos as $ano)
                        <th class="text-right">{{ \App\Support\DinheiroBr::formatar($totalAno[$ano] ?? 0) }}</th>
                    @endforeach
                    <th class="text-right">{{ \App\Support\DinheiroBr::formatar($totalGeral) }}</th>
                </tr>
            </x-slot:foot>
        </x-table>
    </div>
</div>
