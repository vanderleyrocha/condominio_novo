<div class="space-y-6">
    <x-page-header title="Dashboard" subtitle="Visão geral das finanças do condomínio" />

    <div class="grid gap-4 sm:gap-6 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Saldo do mês corrente" icon="credit-card" tone="brand">
            <span class="{{ $saldoMes < 0 ? 'text-red-600' : 'text-slate-900' }}">
                R$ {{ \App\Support\DinheiroBr::formatar($saldoMes) }}
            </span>
            <x-slot:footer>
                Saldo anterior: R$ {{ \App\Support\DinheiroBr::formatar($saldoAnterior) }} |
                Movimento do mês: R$ {{ \App\Support\DinheiroBr::formatar($movimentoMes) }}
            </x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Taxas em aberto" icon="exclamation-triangle" tone="danger">
            <span class="text-red-600">{{ $emAbertoQuantidade }}</span>
            <x-slot:footer>
                Total devido: R$ {{ \App\Support\DinheiroBr::formatar($emAbertoTotal) }}
            </x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Última receita" icon="arrow-trending-up" tone="success">
            @if ($ultimaReceita)
                <span class="text-emerald-600">R$ {{ \App\Support\DinheiroBr::formatar($ultimaReceita->valor) }}</span>
                <x-slot:footer>
                    <span class="block truncate">{{ $ultimaReceita->data_lancamento->format('d/m/Y') }} — {{ $ultimaReceita->descricao }}</span>
                </x-slot:footer>
            @else
                <span class="text-sm font-normal text-slate-500">Nenhum registro encontrado.</span>
            @endif
        </x-stat-card>

        <x-stat-card label="Última despesa" icon="arrow-trending-down" tone="warning">
            @if ($ultimaDespesa)
                <span class="text-red-600">R$ {{ \App\Support\DinheiroBr::formatar($ultimaDespesa->valor) }}</span>
                <x-slot:footer>
                    <span class="block truncate">{{ $ultimaDespesa->data_lancamento->format('d/m/Y') }} — {{ $ultimaDespesa->descricao }}</span>
                </x-slot:footer>
            @else
                <span class="text-sm font-normal text-slate-500">Nenhum registro encontrado.</span>
            @endif
        </x-stat-card>
    </div>

    <div class="card">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h3 class="card-title">Últimos pagamentos</h3>
            <a href="{{ route('pagamentos.index') }}"
               class="rounded text-sm font-medium text-brand transition hover:text-brand-dark hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                Ver todos
            </a>
        </div>
        <x-table class="-mx-6 px-6">
            <x-slot:head>
                <tr>
                    <th>Data</th>
                    <th>Pagador</th>
                    <th>Unidade</th>
                    <th>Descrição</th>
                    <th class="text-right">Valor (R$)</th>
                </tr>
            </x-slot:head>
            @forelse ($ultimosPagamentos as $pagamento)
                <tr wire:key="painel-pg-{{ $pagamento->id }}">
                    <td class="whitespace-nowrap">{{ $pagamento->data_pagamento->format('d/m/Y') }}</td>
                    <td>{{ $pagamento->pessoa->nome ?? '-' }}</td>
                    <td>{{ $pagamento->unidade->identificacao ?? '-' }}</td>
                    <td>
                        {{ $pagamento->descricao }}
                        @if ($pagamento->isEstorno())
                            <span class="badge badge-danger ml-1">ESTORNO</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($pagamento->valor_total) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-8 text-center text-slate-500">Nenhum pagamento registrado.</td>
                </tr>
            @endforelse
        </x-table>
    </div>
</div>
