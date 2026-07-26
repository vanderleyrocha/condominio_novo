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
    </div>

    {{-- Gráfico receitas × despesas por ano --}}
    <div class="card">
        <h2 class="card-title mb-4">Receitas × Despesas por ano</h2>
        <div wire:ignore x-data="graficoResumo(@js($graficoDados))"
             x-on:resumo-grafico-atualizado.window="atualizar($event.detail.dados)"
             class="h-72">
            <canvas x-ref="canvas" role="img" aria-label="Gráfico de barras de receitas e despesas por ano"></canvas>
        </div>
    </div>

    {{-- Matriz ano × unidades --}}
    <div class="card">
        <h2 class="card-title mb-4">Resumo das receitas e despesas</h2>
        <x-table>
            <x-slot:head>
                <tr>
                    <th rowspan="2" class="sticky left-0 z-10 bg-slate-50 text-left">Ano</th>
                    <th colspan="{{ count($unidades) + 2 }}" class="text-center">Receitas</th>
                    <th rowspan="2" class="text-right">Despesas</th>
                </tr>
                <tr>
                    @foreach ($unidades as $unidade)
                        <th class="text-right">{{ $unidade }}</th>
                    @endforeach
                    <th class="text-right">Outras</th>
                    <th class="text-right">Total</th>
                </tr>
            </x-slot:head>
            @forelse ($resumo as $ano => $dados)
                @php $totalAno = 0; @endphp
                <tr wire:key="resumo-{{ $ano }}">
                    <th class="sticky left-0 z-10 bg-white px-4 text-left">{{ $ano }}</th>
                    @foreach ($unidades as $unidade)
                        <td class="text-right tabular-nums">
                            {{ (isset($dados[$unidade]) && $dados[$unidade] > 0) ? \App\Support\DinheiroBr::formatar($dados[$unidade]) : '-' }}
                        </td>
                        @php $totalAno += $dados[$unidade] ?? 0; @endphp
                    @endforeach
                    <td class="text-right tabular-nums">
                        {{ (isset($dados['receita']) && $dados['receita'] > 0) ? \App\Support\DinheiroBr::formatar($dados['receita']) : '-' }}
                    </td>
                    @php $totalAno += $dados['receita'] ?? 0; @endphp
                    <td class="text-right font-medium tabular-nums">{{ \App\Support\DinheiroBr::formatar($totalAno) }}</td>
                    <td class="text-right tabular-nums">
                        {{ (isset($dados['despesas']) && $dados['despesas'] > 0) ? \App\Support\DinheiroBr::formatar($dados['despesas']) : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($unidades) + 4 }}" class="py-6 text-center text-slate-500">
                        Nenhum movimento encontrado para o período.
                    </td>
                </tr>
            @endforelse
            <x-slot:foot>
                <tr class="font-semibold">
                    <th class="sticky left-0 z-10 bg-slate-50 text-left">Total</th>
                    @foreach ($unidades as $unidade)
                        <th class="text-right tabular-nums">
                            {{ (isset($totalUnidade[$unidade]) && $totalUnidade[$unidade] > 0) ? \App\Support\DinheiroBr::formatar($totalUnidade[$unidade]) : '-' }}
                        </th>
                    @endforeach
                    <th class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($totalReceita) }}</th>
                    <th class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($totalReceitas) }}</th>
                    <th class="text-right tabular-nums">{{ \App\Support\DinheiroBr::formatar($totalDespesa) }}</th>
                </tr>
            </x-slot:foot>
        </x-table>
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
