{{-- Paridade de conteúdo: resources/views/mensalidades/recibo.blade.php (legado) --}}
@extends('pdf.layout')

@section('title', $title)

@section('styles')
    <style>
        h1 {
            font-size: 26pt;
        }

        h3 {
            font-size: 16pt;
        }

        h4 {
            font-size: 13pt;
            font-variant: small-caps;
        }

        .corpo-recibo {
            text-align: justify;
            font-size: 1.2rem;
            margin-top: 30px;
            text-indent: 5em;
        }

        .data-recibo {
            text-align: right;
            font-size: 1.2rem;
        }

        #assinatura {
            margin-top: 40px;
            text-align: center;
        }

        #assinatura img {
            display: block;
            margin: 0 auto -18px auto;
            width: 28%;
            height: 60px;
        }

        .assinatura-nome {
            font-size: 1.2rem;
            line-height: 1.1;
            font-weight: bold;
            text-align: center;
        }

        .assinatura-cargo {
            line-height: 1.1;
            font-size: 1.2rem;
            text-align: center;
        }
    </style>
@endsection

@section('content')
    <h1 class="text-center">{{ $title }}</h1>
    <h3 class="text-center">{{ $subTitle }}</h3>

    <br><br><br>

    <h4 class="text-center">RECIBO</h4>

    <br><br>

    <p class="corpo-recibo">
        {{ $recibo }}
    </p>

    <br><br><br>

    <p class="data-recibo">
        {{ $data }}
    </p>

    <br><br><br><br>

    <div id="assinatura">
        @php
            $assinaturaImagem = public_path(\App\Support\ConfiguracoesCondominio::get('assinatura_imagem', 'assets/img/Ass Doneska2.png'));
        @endphp
        @if (file_exists($assinaturaImagem))
            <img src="{{ $assinaturaImagem }}" alt="Assinatura">
        @endif
        <p class="assinatura-nome">{{ \App\Support\ConfiguracoesCondominio::assinaturaRecibo() }}</p>
        <p class="assinatura-cargo">
            {{ \App\Support\ConfiguracoesCondominio::get('assinatura_cargo', 'Responsável pela arrecadação das contribuições dos moradores') }}
        </p>
    </div>
@endsection
