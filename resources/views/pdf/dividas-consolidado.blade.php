{{-- Paridade de conteúdo: resources/views/dividas/print_all.blade.php (legado) --}}
@extends('pdf.layout')

@section('title', $title)

@section('styles')
    <style>
        @page {
            margin: 0.2in 0.5in 0.2in 0.5in;
            padding: 0;
        }

        h1 {
            font-size: 22pt;
        }

        table {
            line-height: 1.3;
            font-size: 10pt;
        }

        thead th {
            background-color: #cfe2ff;
        }
    </style>
@endsection

@section('content')
    <h1 class="text-center">{{ $title }}</h1>
    <p class="text-center">{{ $subTitle }}</p>
    <table class="table-bordered" width="100%">
        <thead>
            <tr>
                <th>Imóvel</th>
                @foreach ($anos as $ano)
                    <th class="text-center">{{ $ano }}</th>
                    @php
                        $total_ano[$ano] = 0;
                    @endphp
                @endforeach
                <th class="text-right">Total</th>
            </tr>
        </thead>
        @php
            $total_geral = 0;
        @endphp
        <tbody>
            @forelse($dividas as $imovel => $divida)
                <tr>
                    <td>{{ $imovel }}</td>
                    @php $total = 0; @endphp
                    @foreach($anos as $ano)
                        <td class="text-right">
                            {{ (isset($divida[$ano]) && $divida[$ano]) ? \App\Support\DinheiroBr::formatar($divida[$ano]) : "-" }}
                        </td>
                        @php
                            $total += $divida[$ano] ?? 0;
                            $total_geral += $divida[$ano] ?? 0;
                            $total_ano[$ano] += $divida[$ano] ?? 0;
                        @endphp
                    @endforeach
                    <th class="text-right text-bold">
                        {{ $total == 0 ? "-" : \App\Support\DinheiroBr::formatar($total) }}
                    </th>
                </tr>
            @empty

            @endforelse
        </tbody>
        <tfoot>
            <tr class="bg-light">
                <th class="text-center">Total</th>
                @foreach($anos as $ano => $value)
                    <th class="text-right">
                        {{ $total_ano[$ano] == 0 ? "-" : \App\Support\DinheiroBr::formatar($total_ano[$ano]) }}
                    </th>
                @endforeach
                <th class="text-right">
                    {{ $total_geral == 0 ? "-" : \App\Support\DinheiroBr::formatar($total_geral) }}
                </th>
            </tr>
        </tfoot>
    </table>
@endsection
