<div class="space-y-6">
    <div>
        <h2 class="page-title">Dashboard</h2>
        <p class="mt-1 text-sm text-slate-500">Visão geral das finanças do condomínio</p>
    </div>

    <div class="grid gap-4 sm:gap-6 md:grid-cols-2 xl:grid-cols-4">
        <div class="card">
            <div class="flex items-center justify-between gap-2">
                <p class="section-label">Saldo do mês corrente</p>
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-light text-brand" aria-hidden="true">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums {{ $saldoMes < 0 ? 'text-red-600' : 'text-slate-900' }}">
                R$ {{ \App\Support\DinheiroBr::formatar($saldoMes) }}
            </p>
            <p class="mt-1.5 text-xs text-slate-500">
                Saldo anterior: R$ {{ \App\Support\DinheiroBr::formatar($saldoAnterior) }} |
                Movimento do mês: R$ {{ \App\Support\DinheiroBr::formatar($movimentoMes) }}
            </p>
        </div>

        <div class="card">
            <div class="flex items-center justify-between gap-2">
                <p class="section-label">Taxas em aberto</p>
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600" aria-hidden="true">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums text-red-600">{{ $emAbertoQuantidade }}</p>
            <p class="mt-1.5 text-xs text-slate-500">Total devido: R$ {{ \App\Support\DinheiroBr::formatar($emAbertoTotal) }}</p>
        </div>

        <div class="card">
            <div class="flex items-center justify-between gap-2">
                <p class="section-label">Última receita</p>
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600" aria-hidden="true">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                    </svg>
                </span>
            </div>
            @if ($ultimaReceita)
                <p class="mt-3 text-2xl font-bold tabular-nums text-emerald-600">R$ {{ \App\Support\DinheiroBr::formatar($ultimaReceita->valor) }}</p>
                <p class="mt-1.5 truncate text-xs text-slate-500">{{ $ultimaReceita->data_lancamento->format('d/m/Y') }} — {{ $ultimaReceita->descricao }}</p>
            @else
                <p class="mt-3 text-sm text-slate-500">Nenhum registro encontrado.</p>
            @endif
        </div>

        <div class="card">
            <div class="flex items-center justify-between gap-2">
                <p class="section-label">Última despesa</p>
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600" aria-hidden="true">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181" />
                    </svg>
                </span>
            </div>
            @if ($ultimaDespesa)
                <p class="mt-3 text-2xl font-bold tabular-nums text-red-600">R$ {{ \App\Support\DinheiroBr::formatar($ultimaDespesa->valor) }}</p>
                <p class="mt-1.5 truncate text-xs text-slate-500">{{ $ultimaDespesa->data_lancamento->format('d/m/Y') }} — {{ $ultimaDespesa->descricao }}</p>
            @else
                <p class="mt-3 text-sm text-slate-500">Nenhum registro encontrado.</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h3 class="card-title">Últimos pagamentos</h3>
            <a href="{{ route('pagamentos-novo.index') }}"
               class="rounded text-sm font-medium text-brand transition hover:text-brand-dark hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                Ver todos
            </a>
        </div>
        <div class="-mx-6 overflow-x-auto px-6">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Pagador</th>
                        <th>Unidade</th>
                        <th>Descrição</th>
                        <th class="text-right">Valor (R$)</th>
                    </tr>
                </thead>
                <tbody>
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
                </tbody>
            </table>
        </div>
    </div>
</div>
