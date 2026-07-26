{{-- Modelo novo (Fase 4): resumo por intervalo via pagamento_taxa + lançamentos.
     Mesmo conteúdo do pdf/resumo-intervalo.blade.php (EX-04/DEV-12 mantidos). --}}
@extends('pdf.layout')

@section('title', 'Receitas e despesas')

@section('styles')
    <style>
        @page {
            margin: 0.5in 0.5in;
            padding: 0;
        }

        body {
            color: #333;
        }

        h1, h2, h3, h4 {
            color: #2c3e50;
        }

        h1 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        h2 {
            font-size: 18px;
            margin: 10px 0 10px 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        h3 {
            font-size: 16px;
            margin: 5px 0 10px 0;
        }

        table {
            line-height: 1.3;
            font-size: 15px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }

        .periodo {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        .table td,
        .table th {
            padding: 8px;
            border: 1px solid #dee2e6;
        }

        .total-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 10px 15px;
            margin: 15px 0;
            font-weight: bold;
        }

        .saldo-final {
            background-color: #e8f4fd;
            border: 1px solid #b8daff;
            border-radius: 20px;
            padding: 10px 15px;
            margin: 20px 0 10px 0;
            font-weight: bold;
            font-size: 14px;
        }

        .valor-positivo {
            color: #28a745;
        }

        .valor-negativo {
            color: #dc3545;
        }

        .font-1-5 {
            font-size: 1.5rem;
        }
    </style>
@endsection

@section('content')
    <div class="header">
        <h1>{{ \App\Support\ConfiguracoesCondominio::nomeCondominio() }}</h1>
        <h2>{{ __('Resumo Financeiro') }}</h2>
        <p class="periodo">
            {{ __('Período: ' . date('d/m/Y', strtotime($de)) . ' a ' . date('d/m/Y', strtotime($ate))) }}</p>
    </div>

    <div class="card">
        <h3>Saldo Anterior</h3>
        <p class="font-1-5">Em {{ date('d/m/Y', strtotime($de)) }}:
            <span class="font-weight-bold {{ $saldo >= 0 ? 'valor-positivo' : 'valor-negativo' }}">
                R$ {{ \App\Support\DinheiroBr::formatar($saldo) }}
            </span>
        </p>
    </div>

    <div class="card">
        <h3>Receitas</h3>

        @php
            $total_receita = 0;
        @endphp
        <table class="table">
            <thead>
                <tr>
                    <th width="15%">Data</th>
                    <th width="65%">Descrição</th>
                    <th width="20%" class="text-right">Valor (R$)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($aplicacoes as $aplicacao)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($aplicacao->data_pagamento)->format('d/m/Y') }}</td>
                        <td>{{ 'Unidade ' . $aplicacao->identificacao . " - Taxa " . str_pad((string) $aplicacao->competencia_mes, 2, "0", STR_PAD_LEFT) . "/" . $aplicacao->competencia_ano }}</td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($aplicacao->valor_aplicado) }}</td>
                    </tr>
                    @php $total_receita += $aplicacao->valor_aplicado; @endphp
                @empty
                    <tr>
                        <td colspan="3" class="text-center">Nenhum pagamento de taxa registrado</td>
                    </tr>
                @endforelse

                @forelse ($receitas as $receita)
                    <tr>
                        <td>{{ $receita->data_lancamento->format('d/m/Y') }}</td>
                        <td>{{ ucfirst($receita->descricao) }}</td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($receita->valor) }}</td>
                    </tr>
                    @php $total_receita += $receita->valor; @endphp
                @empty
                    <tr>
                        <td colspan="3" class="text-center">Nenhuma receita adicional registrada</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="total-box">
            <span class="font-1-5">
                {{ __("Total de Receitas no Período: R$ " . \App\Support\DinheiroBr::formatar($total_receita)) }}
            </span>
        </div>
    </div>

    <div class="card">
        <h3>Despesas</h3>

        <table class="table">
            <thead>
                <tr>
                    <th width="15%">Data</th>
                    <th width="65%">Descrição</th>
                    <th width="20%" class="text-right">Valor (R$)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $total_despesa = 0;
                @endphp
                @forelse ($despesas as $despesa)
                    <tr>
                        <td>{{ $despesa->data_lancamento->format('d/m/Y') }}</td>
                        <td>{{ ucfirst($despesa->descricao) }}</td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($despesa->valor) }}</td>
                    </tr>
                    @php $total_despesa += $despesa->valor; @endphp
                @empty
                    <tr>
                        <td colspan="3" class="text-center">Nenhuma despesa registrada</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="total-box">
            <span class="font-1-5">
                {{ __("Total de Despesas no Período: R$ " . \App\Support\DinheiroBr::formatar($total_despesa)) }}
            </span>
        </div>
    </div>

    <div class="saldo-final">
        <span class="font-1-5">
            {{ __(
                'Saldo em ' .
                    date('d/m/Y', strtotime($ate)) .
                    ": R$" .
                    \App\Support\DinheiroBr::formatar($saldo) .
                    " + R$" .
                    \App\Support\DinheiroBr::formatar($total_receita) .
                    " - R$" .
                    \App\Support\DinheiroBr::formatar($total_despesa) .
                    " = R$" .
                    \App\Support\DinheiroBr::formatar($saldo + $total_receita - $total_despesa),
            ) }}
        </span>
    </div>

    <div class="saldo-final">
        <span class="font-1-5">
            {{ __(
                'Do saldo de R$' . \App\Support\DinheiroBr::formatar($saldo + $total_receita - $total_despesa)
                . ', ' . \App\Support\DinheiroBr::formatar($poupanca + $juros_poupanca)
                . ' são reservados para ' . \App\Support\ConfiguracoesCondominio::get('finalidade_reserva', 'pintura do prédio') . '. '
                . 'Restando disponível em caixa, o valor de R$ '
                . \App\Support\DinheiroBr::formatar($saldo + $total_receita - $total_despesa - $poupanca - $juros_poupanca),
            ) }}
        </span>
    </div>

    <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #777;">
        Relatório gerado em {{ date('d/m/Y H:i') }} - {{ \App\Support\ConfiguracoesCondominio::get('url_sistema', 'http://r4.condominio.space/') }}
    </div>
@endsection
