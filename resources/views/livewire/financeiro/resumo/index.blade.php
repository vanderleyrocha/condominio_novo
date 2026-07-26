<div class="space-y-6">
    <div class="card">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <h2 class="text-lg font-semibold text-slate-900">Resumo das receitas e despesas</h2>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="label">Apartir de</label>
                    <input type="date" wire:model.live="apartirDe" class="input">
                </div>
                <a href="{{ route('pdf.resumo', array_filter(['apartir_de' => $apartirDe])) }}" target="_blank"
                   class="btn btn-primary">Download</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th rowspan="2" class="text-left">Ano</th>
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
                </thead>
                <tbody>
                    @forelse ($resumo as $ano => $dados)
                        @php $totalAno = 0; @endphp
                        <tr wire:key="resumo-{{ $ano }}">
                            <th class="text-left">{{ $ano }}</th>
                            @foreach ($unidades as $unidade)
                                <td class="text-right">
                                    {{ (isset($dados[$unidade]) && $dados[$unidade] > 0) ? \App\Support\DinheiroBr::formatar($dados[$unidade]) : '-' }}
                                </td>
                                @php $totalAno += $dados[$unidade] ?? 0; @endphp
                            @endforeach
                            <td class="text-right">
                                {{ (isset($dados['receita']) && $dados['receita'] > 0) ? \App\Support\DinheiroBr::formatar($dados['receita']) : '-' }}
                            </td>
                            @php $totalAno += $dados['receita'] ?? 0; @endphp
                            <td class="text-right">{{ \App\Support\DinheiroBr::formatar($totalAno) }}</td>
                            <td class="text-right">
                                {{ (isset($dados['despesas']) && $dados['despesas'] > 0) ? \App\Support\DinheiroBr::formatar($dados['despesas']) : '-' }}
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <th class="text-left">Total</th>
                        @foreach ($unidades as $unidade)
                            <th class="text-right">
                                {{ (isset($totalUnidade[$unidade]) && $totalUnidade[$unidade] > 0) ? \App\Support\DinheiroBr::formatar($totalUnidade[$unidade]) : '-' }}
                            </th>
                        @endforeach
                        <th class="text-right">{{ \App\Support\DinheiroBr::formatar($totalReceita) }}</th>
                        <th class="text-right">{{ \App\Support\DinheiroBr::formatar($totalGeral + $totalReceita) }}</th>
                        <th class="text-right">{{ \App\Support\DinheiroBr::formatar($totalDespesa) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-6 rounded-xl bg-slate-900 p-5 text-center text-white">
            <span class="text-2xl">Saldo:
                <span class="font-bold">
                    @if ($saldo != 0)
                        {{ \App\Support\DinheiroBr::formatar($saldo) }} + {{ \App\Support\DinheiroBr::formatar($totalGeral + $totalReceita - $totalDespesa) }} = {{ \App\Support\DinheiroBr::formatar($totalGeral + $totalReceita - $totalDespesa + $saldo) }}
                    @else
                        {{ \App\Support\DinheiroBr::formatar($totalGeral + $totalReceita - $totalDespesa + $saldo) }}
                    @endif
                </span>
            </span>
        </div>
    </div>

    @if ($cobrancas->isNotEmpty())
        <div class="card">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Cobranças extraordinárias apuradas</h3>
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Cobrança</th>
                        <th class="text-right">Apurado em taxas</th>
                        <th class="text-right">Receitas vinculadas</th>
                        <th class="text-right">Total apurado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cobrancas as $cobranca)
                        @php
                            $pivots = (float) ($cobranca->total_taxas ?? 0);
                            $vinculadas = (float) ($receitasPorCobranca[$cobranca->id] ?? 0);
                        @endphp
                        <tr wire:key="resumo-cobranca-{{ $cobranca->id }}">
                            <td>{{ $cobranca->nome }}</td>
                            <td class="text-right">{{ \App\Support\DinheiroBr::formatar($pivots) }}</td>
                            <td class="text-right">{{ \App\Support\DinheiroBr::formatar($vinculadas) }}</td>
                            <td class="text-right font-semibold">{{ \App\Support\DinheiroBr::formatar($pivots + $vinculadas) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
