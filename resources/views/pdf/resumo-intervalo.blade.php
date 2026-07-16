{{-- Paridade de conteúdo: resources/views/resumo_intervalo_print.blade.php (legado).
     DEV-11: nome do condomínio e URL parametrizados.
     DEV-12: reserva (poupança + juros) apurada por cobranças extras; texto default literal. --}}
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
        <h1>{{ \App\Support\ParametrosCondominio::nomeCondominio() }}</h1>
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
                @forelse ($mensalidades as $mensalidade)
                    <tr>
                        <td>{{ $mensalidade->pago_em->format('d/m/Y') }}</td>
                        <td>{{ 'Apartamento ' . $mensalidade->imovel->nome . " - Mensalidade " . str_pad((string) $mensalidade->mes, 2, "0", STR_PAD_LEFT) . "/" . $mensalidade->ano }}</td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($mensalidade->valor_pago) }}</td>
                    </tr>
                    @php $total_receita += $mensalidade->valor_pago; @endphp
                @empty
                    <tr>
                        <td colspan="3" class="text-center">Nenhuma mensalidade registrada</td>
                    </tr>
                @endforelse

                @forelse ($receitas as $receita)
                    <tr>
                        <td>{{ $receita->data->format('d/m/Y') }}</td>
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
                        <td>{{ $despesa->data->format('d/m/Y') }}</td>
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
                . ' são reservados para ' . \App\Support\ParametrosCondominio::get('finalidade_reserva', 'pintura do prédio') . '. '
                . 'Restando disponível em caixa, o valor de R$ '
                . \App\Support\DinheiroBr::formatar($saldo + $total_receita - $total_despesa - $poupanca - $juros_poupanca),
            ) }}
        </span>
    </div>

    <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #777;">
        Relatório gerado em {{ date('d/m/Y H:i') }} - {{ \App\Support\ParametrosCondominio::get('url_sistema', 'http://r4.condominio.space/') }}
    </div>
@endsection
