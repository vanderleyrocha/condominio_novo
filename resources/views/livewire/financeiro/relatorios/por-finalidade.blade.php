<div class="space-y-6">
    <div class="card">
        <x-page-header title="Arrecadação por finalidade" class="mb-4">
            <div class="w-44">
                <x-input label="Posição em" type="date" wire:model.live="ate" />
            </div>
        </x-page-header>

        {{-- Nota obrigatória (§2 do plano): a ordem de quitação muda a leitura --}}
        <div class="alert alert-info mb-4 text-sm">
            <strong>Como o valor pago é distribuído:</strong> numa mensalidade composta, o pagamento quita os itens
            na ordem em que aparecem na composição — a taxa condominial primeiro, as contribuições depois.
            Um pagamento parcial de R$ 75,00 numa mensalidade de 100 + 50 conta 75 para o custeio e 0 para a
            contribuição. Por isso a coluna <em>a receber</em> importa tanto quanto a de <em>arrecadado</em>.
        </div>

        @if ($temTaxaSemComposicao)
            <div class="alert alert-warning mb-4 text-sm">
                Existem taxas sem composição — os valores delas não aparecem aqui.
                Rode <code>php artisan taxas:decompor-composicao</code> para fazer o backfill.
            </div>
        @endif

        <x-table class="-mx-6 px-6">
            <x-slot:head>
                <tr>
                    <th>Finalidade</th>
                    <th class="text-right">Cobrado em taxas</th>
                    <th class="text-right">Arrecadado em taxas</th>
                    <th class="text-right">Outras receitas</th>
                    <th class="text-right">Arrecadado total</th>
                    <th class="text-right">Gasto</th>
                    <th class="text-right">Saldo do fundo</th>
                    <th class="text-right">A receber</th>
                </tr>
            </x-slot:head>
            @forelse ($linhas as $linha)
                <tr wire:key="finalidade-{{ $linha['finalidade']?->id ?? 'sem' }}">
                    <td>
                        {{ $linha['finalidade']?->nome ?? 'Sem finalidade específica' }}
                        @if ($linha['finalidade']?->restrita)
                            <span class="badge badge-warning" title="Não entra no disponível para custeio ordinário">Vinculada</span>
                        @endif
                        @if ($linha['finalidade']?->meta_valor !== null)
                            <p class="text-xs text-slate-500">
                                Meta: R$ {{ \App\Support\DinheiroBr::formatar($linha['finalidade']->meta_valor) }}
                            </p>
                        @endif
                    </td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['cobrado']) }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['arrecadado_taxas']) }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['receitas']) }}</td>
                    <td class="text-right font-semibold tabular-nums text-emerald-600">
                        R$ {{ \App\Support\DinheiroBr::formatar($linha['arrecadado']) }}
                    </td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['gasto']) }}</td>
                    <td class="text-right font-semibold tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['saldo']) }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['a_receber']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="py-6 text-center text-slate-500">Nenhuma arrecadação registrada.</td>
                </tr>
            @endforelse
            <x-slot:foot>
                <tr class="font-semibold">
                    <td colspan="4">Total</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($totalArrecadado) }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($totalGasto) }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($totalSaldo) }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($totalAReceber) }}</td>
                </tr>
            </x-slot:foot>
        </x-table>
    </div>
</div>
