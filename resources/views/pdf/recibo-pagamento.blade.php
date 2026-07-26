{{-- Modelo novo (Fase 4): recibo de pagamento sobre pagamentos_novo/taxas.
     Mesmo conteúdo do pdf/recibo-pagamento.blade.php, lendo ConfiguracoesCondominio. --}}
@extends('pdf.layout')

@section('title', 'Recibo de Pagamento')

@section('styles')
    <style>
        body {
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
        }

        .box {
            border: 1px solid #000;
            padding: 10px;
            margin-bottom: 15px;
        }

        .row {
            width: 100%;
            margin-bottom: 5px;
        }

        .label {
            font-weight: bold;
        }

        table {
            margin-top: 10px;
        }

        table th, table td {
            border: 1px solid #000;
            padding: 5px;
        }

        table th {
            background-color: #f0f0f0;
        }

        p {
            text-indent: 5em;
        }

        #assinatura {
            position: relative;
        }

        #assinatura img {
            position: absolute;
            top: -60px;
            width: 30%;
            height: 100px;
            margin-left: 40%;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
        }
    </style>
@endsection

@section('content')
    <div class="header">
        <h1 class="text-center">{{ mb_strtoupper(\App\Support\ConfiguracoesCondominio::nomeCondominio()) }}</h1>
        <h2>RECIBO DE PAGAMENTO</h2>
        <p>Nº {{ $pagamento->id }}</p>
    </div>

    {{-- Dados do Pagamento --}}
    <div class="box">
        <div class="row">
            <span class="label">Data:</span>
            {{ $pagamento->data_pagamento->format('d/m/Y') }}
        </div>

        <div class="row">
            <span class="label">Descrição:</span>
            {{ $pagamento->descricao }}
        </div>

        <div class="row">
            <span class="label">Valor Total:</span>
            R$ {{ \App\Support\DinheiroBr::formatar($pagamento->valor_total) }}
        </div>

        <div class="row">
            <span class="label">Forma de pagamento:</span>
            {{ $pagamento->forma_pagamento->rotulo() }}
        </div>
    </div>

    {{-- Pagador / Unidade --}}
    <div class="box">
        <div class="row">
            <span class="label">Pagador:</span>
            {{ $pagamento->pessoa->nome }}
        </div>

        <div class="row">
            <span class="label">Unidade:</span>
            {{ $pagamento->unidade->identificacao ?? '-' }}
        </div>
    </div>

    {{-- Taxas --}}
    <div class="box">
        <span class="label">Taxas Quitadas</span>

        <table>
            <thead>
                <tr>
                    <th>Ano</th>
                    <th>Mês</th>
                    <th>Vencimento</th>
                    <th class="text-right">Valor Aplicado (R$)</th>
                </tr>
            </thead>

            <tbody>
                @foreach($pagamento->taxasCondominiais as $taxa)
                    <tr>
                        <td class="text-center">{{ $taxa->competencia_ano }}</td>
                        <td class="text-center">{{ $taxa->competencia_mes }}</td>
                        <td class="text-center">{{ $taxa->vencimento->format('d/m/Y') }}</td>
                        <td class="text-right">
                            {{ \App\Support\DinheiroBr::formatar($taxa->pivot->valor_aplicado) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <th colspan="3" class="text-right">
                        Total Aplicado
                    </th>
                    <th class="text-right">
                        R$ {{ \App\Support\DinheiroBr::formatar($pagamento->taxasCondominiais->sum('pivot.valor_aplicado')) }}
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        Documento gerado em {{ now()->format('d/m/Y H:i') }}<br>
        Sistema de Gestão de Condomínio
    </div>

    <br><br><br><br><br><br>
    <div id="assinatura" class="text-center">
        @php
            $assinaturaImagem = public_path(\App\Support\ConfiguracoesCondominio::get('assinatura_imagem', 'assets/img/Ass Doneska2.png'));
        @endphp
        @if (file_exists($assinaturaImagem))
            <img src="{{ $assinaturaImagem }}" alt="Assinatura">
        @endif
        <p class="text-center">{{ \App\Support\ConfiguracoesCondominio::assinaturaRecibo() }}</p>
        <p class="text-center">{{ \App\Support\ConfiguracoesCondominio::get('assinatura_cargo', 'Responsável pela arrecadação das contribuições dos moradores') }}</p>
    </div>
@endsection
