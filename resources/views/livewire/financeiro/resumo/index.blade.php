<div class="space-y-6">
    <x-page-header title="Resumo financeiro" subtitle="Receitas e despesas consolidadas por ano">
        <x-input label="A partir de" type="date" wire:model.live="apartirDe" />
        <x-button variant="secondary" :href="route('pdf.resumo', array_filter(['apartir_de' => $apartirDe]))" target="_blank">
            Baixar PDF
        </x-button>
    </x-page-header>

    {{-- Indicadores --}}
    <div class="grid gap-4 sm:gap-6 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Saldo anterior" icon="credit-card" tone="brand">
            R$ {{ \App\Support\DinheiroBr::formatar($saldo) }}
            <x-slot:footer>
                {{ $apartirDe !== '' ? 'Acumulado até '.\Carbon\Carbon::parse($apartirDe)->format('d/m/Y') : 'Sem filtro de data' }}
            </x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Receitas" icon="arrow-trending-up" tone="success">
            <span class="text-emerald-600">R$ {{ \App\Support\DinheiroBr::formatar($totalReceitas) }}</span>
            <x-slot:footer>Taxas condominiais + outras receitas</x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Despesas" icon="arrow-trending-down" tone="danger">
            <span class="text-red-600">R$ {{ \App\Support\DinheiroBr::formatar($totalDespesa) }}</span>
            <x-slot:footer>Lançamentos de despesa do período</x-slot:footer>
        </x-stat-card>

        @if ($saldoVinculado > 0)
            <x-stat-card label="Disponível para custeio" icon="banknotes" tone="brand">
                <span class="{{ $disponivelCusteio < 0 ? 'text-red-600' : 'text-slate-900' }}">
                    R$ {{ \App\Support\DinheiroBr::formatar($disponivelCusteio) }}
                </span>
                <x-slot:footer>
                    Saldo final de {{ \App\Support\DinheiroBr::formatar($saldoFinal) }}
                    − {{ \App\Support\DinheiroBr::formatar($saldoVinculado) }} vinculado a finalidades
                </x-slot:footer>
            </x-stat-card>
        @else
            <x-stat-card label="Saldo final" icon="banknotes" tone="brand">
                <span class="{{ $saldoFinal < 0 ? 'text-red-600' : 'text-slate-900' }}">
                    R$ {{ \App\Support\DinheiroBr::formatar($saldoFinal) }}
                </span>
                <x-slot:footer>
                    @if ($saldo != 0)
                        {{ \App\Support\DinheiroBr::formatar($saldo) }} + {{ \App\Support\DinheiroBr::formatar($totalReceitas - $totalDespesa) }} = {{ \App\Support\DinheiroBr::formatar($saldoFinal) }}
                    @else
                        Receitas − despesas do período
                    @endif
                </x-slot:footer>
            </x-stat-card>
        @endif
    </div>

    {{-- Segregação do saldo: o que é carimbado não custeia despesa corrente --}}
    @if ($vinculadas->isNotEmpty())
        <div class="card">
            <h2 class="card-title mb-4">Recursos vinculados a finalidades</h2>

            <dl class="mb-5 max-w-md space-y-1 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-600">Saldo final em caixa</dt>
                    <dd class="tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($saldoFinal) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-600">(−) Vinculado a finalidades</dt>
                    <dd class="tabular-nums text-amber-600">R$ {{ \App\Support\DinheiroBr::formatar($saldoVinculado) }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-slate-200 pt-1 font-semibold">
                    <dt>(=) Disponível para custeio ordinário</dt>
                    <dd class="tabular-nums {{ $disponivelCusteio < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        R$ {{ \App\Support\DinheiroBr::formatar($disponivelCusteio) }}
                    </dd>
                </div>
            </dl>

            @if ($disponivelCusteio < 0)
                <div class="alert alert-warning mb-4 text-sm">
                    O custeio corrente está usando
                    <strong>R$ {{ \App\Support\DinheiroBr::formatar(abs($disponivelCusteio)) }}</strong>
                    de recursos vinculados — dinheiro arrecadado para uma finalidade específica está
                    pagando despesa ordinária.
                </div>
            @endif

            <x-table>
                <x-slot:head>
                    <tr>
                        <th>Finalidade</th>
                        <th class="text-right">Arrecadado</th>
                        <th class="text-right">Gasto</th>
                        <th class="text-right">Saldo do fundo</th>
                        <th class="text-right">Meta</th>
                    </tr>
                </x-slot:head>
                @foreach ($vinculadas as $linha)
                    @php
                        $meta = $linha['finalidade']->meta_valor;
                    @endphp
                    <tr wire:key="resumo-vinculada-{{ $linha['finalidade']->id }}">
                        <td>
                            {{ $linha['finalidade']->nome }}
                            @if ($linha['a_receber'] > 0)
                                <p class="text-xs text-slate-500">
                                    A receber: R$ {{ \App\Support\DinheiroBr::formatar($linha['a_receber']) }}
                                </p>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['arrecadado']) }}</td>
                        <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['gasto']) }}</td>
                        <td class="text-right font-semibold tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['saldo']) }}</td>
                        <td class="text-right tabular-nums">
                            @if ($meta === null)
                                —
                            @else
                                R$ {{ \App\Support\DinheiroBr::formatar($meta) }}
                                <span class="block text-xs text-slate-500">
                                    {{ (float) $meta > 0 ? round(100 * (float) $linha['arrecadado'] / (float) $meta).'% arrecadado' : '' }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <p class="mt-4 text-sm text-slate-500">
                O saldo destas finalidades está no caixa, mas é dinheiro carimbado: não deve custear
                despesas correntes de manutenção e administração. Marque uma finalidade como vinculada
                em <a href="{{ route('finalidades.index') }}" class="text-brand underline">Finalidades</a>.
            </p>
        </div>
    @endif

    {{-- Gráfico receitas × despesas por ano --}}
    <div class="card">
        <h2 class="card-title mb-4">Receitas × Despesas por ano</h2>
        <div wire:ignore x-data="graficoResumo(@js($graficoDados))"
             x-on:resumo-grafico-atualizado.window="atualizar($event.detail.dados)"
             class="h-72">
            <canvas x-ref="canvas" role="img" aria-label="Gráfico de barras de receitas e despesas por ano"></canvas>
        </div>
    </div>

    {{-- Cobranças extraordinárias --}}
    @if ($cobrancas->isNotEmpty())
        <div class="card">
            <h3 class="card-title mb-4">Cobranças extraordinárias apuradas</h3>
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>Cobrança</th>
                        <th class="text-right">Apurado em taxas</th>
                        <th class="text-right">Receitas vinculadas</th>
                        <th class="text-right">Total apurado</th>
                    </tr>
                </x-slot:head>
                @foreach ($cobrancas as $cobranca)
                    @php
                        $pivots = (float) ($cobranca->total_taxas ?? 0);
                        $vinculadas = (float) ($receitasPorCobranca[$cobranca->id] ?? 0);
                    @endphp
                    <tr wire:key="resumo-cobranca-{{ $cobranca->id }}">
                        <td>{{ $cobranca->nome }}</td>
                        <td class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($pivots) }}</td>
                        <td class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($vinculadas) }}</td>
                        <td class="text-right font-semibold tabular-nums">{{ \App\Support\DinheiroBr::formatar($pivots + $vinculadas) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif
</div>
