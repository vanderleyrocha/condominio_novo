<div class="space-y-6">
    <x-page-header title="Resumo por intervalo" subtitle="Movimentação detalhada do período">
        <x-input label="De" type="date" wire:model="de" />
        <x-input label="Até" type="date" wire:model="ate" />
        <x-button wire:click="$refresh">Atualizar</x-button>
        <x-button variant="secondary" :href="route('pdf.resumo.intervalo', ['de' => $de, 'ate' => $ate])" target="_blank">
            Baixar PDF
        </x-button>
    </x-page-header>

    {{-- Indicadores do período --}}
    <div class="grid gap-4 sm:gap-6 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Saldo anterior" icon="credit-card" tone="brand">
            R$ {{ \App\Support\DinheiroBr::formatar($saldo) }}
            <x-slot:footer>Acumulado até {{ \Carbon\Carbon::parse($de)->format('d/m/Y') }}</x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Receitas do período" icon="arrow-trending-up" tone="success">
            <span class="text-emerald-600">R$ {{ \App\Support\DinheiroBr::formatar($totalReceita) }}</span>
            <x-slot:footer>Taxas aplicadas + outras receitas</x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Despesas do período" icon="arrow-trending-down" tone="danger">
            <span class="text-red-600">R$ {{ \App\Support\DinheiroBr::formatar($totalDespesa) }}</span>
            <x-slot:footer>Lançamentos de despesa</x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Saldo final" icon="banknotes" tone="brand">
            <span class="{{ $saldoFinal < 0 ? 'text-red-600' : 'text-slate-900' }}">
                R$ {{ \App\Support\DinheiroBr::formatar($saldoFinal) }}
            </span>
            <x-slot:footer>
                Em {{ \Carbon\Carbon::parse($ate)->format('d/m/Y') }}:
                {{ \App\Support\DinheiroBr::formatar($saldo) }} + {{ \App\Support\DinheiroBr::formatar($totalReceita) }} − {{ \App\Support\DinheiroBr::formatar($totalDespesa) }}
            </x-slot:footer>
        </x-stat-card>
    </div>

    {{-- Receitas --}}
    <div class="card">
        <h3 class="card-title mb-4">Receitas</h3>
        <x-table>
            <x-slot:head>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th class="text-right">Valor</th>
                </tr>
            </x-slot:head>
            @foreach ($aplicacoes as $aplicacao)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($aplicacao->data_pagamento)->format('d/m/Y') }}</td>
                    <td>
                        {{ 'Unidade '.$aplicacao->identificacao
                            .' - Taxa '.str_pad((string) $aplicacao->competencia_mes, 2, '0', STR_PAD_LEFT)
                            .'/'.$aplicacao->competencia_ano }}
                    </td>
                    <td class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($aplicacao->valor_aplicado) }}</td>
                </tr>
            @endforeach
            @foreach ($receitas as $receita)
                <tr wire:key="int-receita-{{ $receita->id }}">
                    <td>{{ $receita->data_lancamento->format('d/m/Y') }}</td>
                    <td>{{ $receita->descricao }}</td>
                    <td class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($receita->valor) }}</td>
                </tr>
            @endforeach
            <x-slot:foot>
                <tr class="font-semibold">
                    <th colspan="2" class="text-left">Total das receitas do período</th>
                    <th class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($totalReceita) }}</th>
                </tr>
            </x-slot:foot>
        </x-table>
    </div>

    {{-- Despesas --}}
    <div class="card">
        <h3 class="card-title mb-4">Despesas</h3>
        <x-table>
            <x-slot:head>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th class="text-right">Valor</th>
                </tr>
            </x-slot:head>
            @foreach ($despesas as $despesa)
                <tr wire:key="int-despesa-{{ $despesa->id }}">
                    <td>{{ $despesa->data_lancamento->format('d/m/Y') }}</td>
                    <td>{{ $despesa->descricao }}</td>
                    <td class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($despesa->valor) }}</td>
                </tr>
            @endforeach
            <x-slot:foot>
                <tr class="font-semibold">
                    <th colspan="2" class="text-left">Total</th>
                    <th class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($totalDespesa) }}</th>
                </tr>
            </x-slot:foot>
        </x-table>
    </div>
</div>
