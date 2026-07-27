{{-- Paridade de conteúdo: resources/views/dividas/print.blade.php +
     dividas/partials/memoria-calculo.blade.php (legado) --}}
@extends('pdf.layout')

@section('title', $title)

@section('styles')
    <style>
        h1 {
            font-size: 26pt;
        }

        h1.bordered {
            border-top: 0.075cm solid #000000;
            border-bottom: 0.075cm solid #000000;
        }

        h4 {
            font-size: 13pt;
            font-variant: small-caps;
        }

        h5 {
            font-style: italic;
            font-size: 11pt;
        }

        .table-resumo-mensal {
            table-layout: fixed;
            width: 100%;
            font-size: 9pt;
            line-height: 1.2;
        }

        .table-resumo-mensal th,
        .table-resumo-mensal td {
            padding: 2mm 1mm;
            text-align: center;
            vertical-align: middle;
        }

        .table-resumo-mensal thead th {
            background-color: #e3ece4;
            font-weight: bold;
            border: 0.5mm solid #000;
        }

        .table-resumo-mensal .col-ano {
            width: 6%;
            font-weight: bold;
        }

        .table-resumo-mensal .col-mes {
            width: 6%;
        }

        .table-resumo-mensal .col-total {
            width: 8%;
            font-weight: bold;
            background-color: #f5f8f5;
        }

        .table-sm {
            font-size: 10pt;
            line-height: 1.2;
        }
    </style>
@endsection

@section('content')
    {{-- TÍTULO --}}
    <h1 class="text-center bordered">{{ $title }}</h1>
    <p class="text-center mb-2">
        {{ $subTitle }} <br>
        <span class="font-normal">
            Valores atualizados pelo IPCA até {{ now()->format('d/m/Y') }}
        </span>
    </p>

    {{-- =========================================================
         TABELA RESUMO ANUAL × MESES
         ========================================================= --}}
    <table class="table-bordered table-resumo-mensal">
        <thead>
            <tr>
                <th class="col-ano">Ano</th>
                <th class="col-mes">Jan</th>
                <th class="col-mes">Fev</th>
                <th class="col-mes">Mar</th>
                <th class="col-mes">Abr</th>
                <th class="col-mes">Mai</th>
                <th class="col-mes">Jun</th>
                <th class="col-mes">Jul</th>
                <th class="col-mes">Ago</th>
                <th class="col-mes">Set</th>
                <th class="col-mes">Out</th>
                <th class="col-mes">Nov</th>
                <th class="col-mes">Dez</th>
                <th class="col-total">Total</th>
            </tr>
        </thead>

        <tbody>
            @php $total_geral = 0; @endphp

            @forelse($mensalidades as $ano => $meses)
                @php $total_ano = 0; @endphp
                <tr>
                    <td class="col-ano bordered">{{ $ano }}</td>

                    @foreach ($meses as $mes)
                        <td class="col-mes {{ $mes['class'] }} bordered">
                            {{ $mes['valor_fmt'] }}
                        </td>
                        @php $total_ano += $mes['valor_num']; @endphp
                    @endforeach

                    <td class="col-total bordered">
                        {{ $total_ano == 0 ? '-' : \App\Support\DinheiroBr::formatar($total_ano) }}
                    </td>

                    @php $total_geral += $total_ano; @endphp
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="text-center bordered">
                        Nenhuma dívida em aberto
                    </td>
                </tr>
            @endforelse
        </tbody>

        <tfoot>
            <tr>
                <th colspan="13" class="text-right bordered">Total Geral</th>
                <th class="col-total bordered">
                    {{ $total_geral == 0 ? '-' : \App\Support\DinheiroBr::formatar($total_geral) }}
                </th>
            </tr>
        </tfoot>
    </table>

    {{-- ESPAÇAMENTO CONTROLADO PARA PDF --}}
    <div style="height: 12px;"></div>

    {{-- =========================================================
         MEMÓRIA DE CÁLCULO DETALHADA
         ========================================================= --}}
    <h4>Memória de Cálculo</h4>

    @php
        $total_geral_original = 0;
        $total_geral_corrigido = 0;
    @endphp

    @foreach ($memoriaCalculo as $ano => $itens)
        <h5>Ano {{ $ano }}</h5>

        @php
            $total_original = 0;
            $total_corrigido = 0;
        @endphp

        <table class="table-bordered table-sm mb-3">
            <thead>
                <tr>
                    <th class="pmhMiddleLeft bordered">Competência</th>
                    <th class="pmhMiddleRight bordered">Valor Original</th>
                    <th class="pmhMiddleRight bordered">IPCA Acumulado (%)</th>
                    <th class="pmhMiddleCenter bordered">Período</th>
                    <th class="pmhMiddleRight bordered">Valor Corrigido</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($itens as $item)
                    @php
                        $total_original += $item['valor_original'];
                        $total_corrigido += $item['valor_corrigido'];

                        $total_geral_original += $item['valor_original'];
                        $total_geral_corrigido += $item['valor_corrigido'];
                    @endphp
                    <tr>
                        <td class="pmhMiddleLeft bordered">
                            {{ $item['competencia'] }}
                            {{-- Composição da mensalidade: discriminada só quando
                                 há mais de um item, para não poluir o histórico --}}
                            @if (count($item['composicao'] ?? []) > 1)
                                <br><span style="font-size: 8pt; font-style: italic;">
                                    {{ implode(' + ', $item['composicao']) }}
                                </span>
                            @endif
                        </td>

                        <td class="pmhMiddleRight bordered">
                            {{ \App\Support\DinheiroBr::formatar($item['valor_original']) }}
                        </td>

                        <td class="pmhMiddleRight bordered">
                            {{ \App\Support\DinheiroBr::formatar($item['ipca_acumulado']) }}
                        </td>

                        <td class="pmhMiddleCenter bordered">
                            {{ $item['periodo'] }}
                        </td>

                        <td class="pmhMiddleRight text-bold bordered">
                            {{ \App\Support\DinheiroBr::formatar($item['valor_corrigido']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr class="bg-light">
                    <th class="pmhMiddleRight bordered">
                        Total do Ano
                    </th>

                    <th class="pmhMiddleRight bordered">
                        {{ \App\Support\DinheiroBr::formatar($total_original) }}
                    </th>

                    <th class="pmhMiddleCenter bordered">—</th>

                    <th class="pmhMiddleCenter bordered">—</th>

                    <th class="pmhMiddleRight text-bold bordered">
                        {{ \App\Support\DinheiroBr::formatar($total_corrigido) }}
                    </th>
                </tr>
            </tfoot>
        </table>
    @endforeach

    {{-- =========================================================
         TOTAIS GERAIS DO RELATÓRIO
         ========================================================= --}}
    <table class="table-bordered table-sm mt-4">
        <thead>
            <tr>
                <th colspan="2" class="pmhMiddleCenter bordered">
                    Totais Gerais do Período
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th class="pmhMiddleRight bordered">
                    Valor Original Total
                </th>
                <td class="pmhMiddleRight bordered">
                    {{ \App\Support\DinheiroBr::formatar($total_geral_original) }}
                </td>
            </tr>
            <tr>
                <th class="pmhMiddleRight bordered">
                    Valor Corrigido Total (IPCA)
                </th>
                <td class="pmhMiddleRight text-bold bordered">
                    {{ \App\Support\DinheiroBr::formatar($total_geral_corrigido) }}
                </td>
            </tr>
            <tr class="bg-light">
                <th class="pmhMiddleRight bordered">
                    Diferença (Correção Monetária)
                </th>
                <td class="pmhMiddleRight text-bold bordered">
                    {{ \App\Support\DinheiroBr::formatar($total_geral_corrigido - $total_geral_original) }}
                </td>
            </tr>
        </tbody>
    </table>
@endsection
