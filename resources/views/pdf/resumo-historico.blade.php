{{-- Paridade de conteúdo: resources/views/resumo_print.blade.php (legado).
     DEV-11: título/subtítulo parametrizados no controller.
     DEV-12: poupança/juros apurados por cobranças extras no controller
     (o template legado não os exibe; contrato de dados preservado). --}}
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
            line-height: 2.6;
            /* 8px: 19 colunas (16 imoveis + Outras + Total + Despesas) precisam
               caber no A4 landscape - em 10px o dompdf cortava as ultimas colunas */
            font-size: 8px;
        }

        table th,
        table td {
            padding: 1px 2px;
        }

        .card-footer {
            background-color: #6c757d;
            color: #fff;
            padding: 2mm;
            margin-top: 3mm;
            text-align: center;
        }

        .font-1-5 {
            font-size: 1.5rem;
        }
    </style>
@endsection

@section('content')
    <h1 class="text-center">{{ $title }}</h1>
    <p class="text-center">{{ $subTitle }}</p>
    @php
        $total_receita = 0;
        $total_despesa = 0;
    @endphp
    <table class="table-bordered">
        <thead>
            <tr>
                <th rowspan="2" class="text-left">Ano</th>
                <th colspan="{{ count($imoveis) + 2 }}" class="text-center">Receitas</th>
                <th rowspan="2" class="text-right">Despesas</th>
            </tr>
            <tr>
                @foreach($imoveis as $imovel)
                    <th class="text-right">{{ $imovel }}</th>
                @endforeach
                <th class="text-right">Outras</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resumo as $ano => $data)
                @php
                    $total_ano = 0;
                @endphp
                <tr>
                    <th class="text-left">{{ $ano }}</th>
                    @foreach($imoveis as $imovel)
                        <td class="text-right">
                            {{
                                (isset($resumo[$ano][$imovel]) && ($resumo[$ano][$imovel] > 0)) ? \App\Support\DinheiroBr::formatar($resumo[$ano][$imovel]) : "-"
                            }}
                        </td>
                        @php
                            $total_ano += isset($resumo[$ano][$imovel]) ? $resumo[$ano][$imovel] : 0;
                        @endphp
                    @endforeach
                    <td class="text-right">
                        {{
                            (isset($resumo[$ano]["receita"]) && ($resumo[$ano]["receita"] > 0)) ? \App\Support\DinheiroBr::formatar($resumo[$ano]["receita"]) : "-"
                        }}
                    </td>
                    @php
                        $total_ano += isset($resumo[$ano]["receita"]) ? $resumo[$ano]["receita"] : 0;
                        $total_receita += isset($resumo[$ano]["receita"]) ? $resumo[$ano]["receita"] : 0;
                    @endphp

                    <td class="text-right">
                        {{ \App\Support\DinheiroBr::formatar($total_ano) }}
                    </td>

                    <td class="text-right">
                        {{
                            (isset($resumo[$ano]["despesas"]) && ($resumo[$ano]["despesas"] > 0)) ? \App\Support\DinheiroBr::formatar($resumo[$ano]["despesas"]) : "-"
                        }}
                    </td>
                    @php
                        $total_despesa += isset($resumo[$ano]["despesas"]) ? $resumo[$ano]["despesas"] : 0;
                    @endphp
                </tr>
            @empty

            @endforelse
        </tbody>
        <tfoot class="bg-light">
            <tr class="bg-light">
                <th class="text-left">Total</th>
                @php
                    $total_geral = 0;
                @endphp
                @foreach($imoveis as $imovel)
                    <th class="text-right">
                        {{ (isset($total_imovel[$imovel]) && ($total_imovel[$imovel] > 0)) ? \App\Support\DinheiroBr::formatar($total_imovel[$imovel]) : "-" }}
                    </th>
                    @php
                        $total_geral += isset($total_imovel[$imovel]) ? $total_imovel[$imovel] : 0;
                    @endphp
                @endforeach
                <th class="text-right">{{ \App\Support\DinheiroBr::formatar($total_receita) }}</th>
                @php
                    $total_geral += $total_receita;
                @endphp
                <th class="text-right">{{ \App\Support\DinheiroBr::formatar($total_geral) }}</th>
                <th class="text-right">{{ \App\Support\DinheiroBr::formatar($total_despesa) }}</th>
            </tr>
        </tfoot>
    </table>
    <div class="card-footer">
        @php
            $saldo_final = $total_geral - $total_despesa;
        @endphp
        <span style="font-size: 1.5rem;">Saldo: <span class="font-weight-bold">{{ \App\Support\DinheiroBr::formatar($saldo_final) }}</span></span>

        {{-- Segregação por destinação: recursos carimbados não custeiam despesa corrente --}}
        @if ($vinculadas->isNotEmpty())
            <table class="table table-sm" style="margin-top: 12px; width: 60%;">
                <tbody>
                    @foreach ($vinculadas as $linha)
                        <tr>
                            <td class="text-left">(−) {{ $linha['finalidade']->nome }} (finalidade vinculada)</td>
                            <td class="text-right">{{ \App\Support\DinheiroBr::formatar($linha['saldo']) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th class="text-left">(=) Disponível para custeio ordinário</th>
                        <th class="text-right">{{ \App\Support\DinheiroBr::formatar($saldo_final - $saldo_vinculado) }}</th>
                    </tr>
                </tbody>
            </table>
            <p style="font-size: 0.8rem;">
                O saldo das finalidades vinculadas está em caixa, mas é destinado exclusivamente a elas —
                não está disponível para despesas correntes de manutenção e administração.
            </p>
        @endif
    </div>
@endsection
