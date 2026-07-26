{{-- Modelo novo (Fase 4): despesas via lancamentos_financeiros + planos_contas.
     Mesmo conteúdo do pdf/despesas-periodo.blade.php. --}}
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
            line-height: 1.2;
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
                <th class="text-left">Data</th>
                <th class="text-left">Descrição</th>
                <th class="text-left">Plano de contas</th>
                <th class="text-right">Valor</th>
            </tr>
        </thead>
        @php
            $total = 0;
        @endphp
        <tbody>
            @forelse($despesas as $despesa)
                <tr>
                    <td class="text-left">{{ $despesa->data_lancamento->format('d/m/Y') }}</td>
                    <td class="text-left">{{ $despesa->descricao }}</td>
                    <td class="text-left">{{ $despesa->planoConta->descricao ?? '-' }}</td>
                    <td class="text-right">{{ \App\Support\DinheiroBr::formatar($despesa->valor) }}</td>
                </tr>
                @php
                    $total += $despesa->valor;
                @endphp
            @empty
            @endforelse
        </tbody>
        <tfoot>
            <tr class="bg-light">
                <th colspan="3" class="text-left">Total</th>
                <th class="text-right">
                    {{ $total == 0 ? '-' : \App\Support\DinheiroBr::formatar($total) }}
                </th>
            </tr>
        </tfoot>
    </table>
@endsection
