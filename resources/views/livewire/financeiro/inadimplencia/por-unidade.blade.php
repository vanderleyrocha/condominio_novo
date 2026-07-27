<div class="space-y-6">
    <div class="card">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">
                Inadimplência da unidade {{ $unidade->identificacao }}
                - {{ $unidade->vinculos->first()?->pessoa->nome ?? 'Não informado' }} (valores sem correção)
            </h2>
            <x-button :href="route('pdf.inadimplencia.unidade', $unidade)" target="_blank">Download</x-button>
        </div>

        <table class="table-modern">
            <thead>
                <tr>
                    <th>Ano</th>
                    <th class="text-right">Valor original (R$)</th>
                    <th class="text-right">Valor corrigido (IPCA)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $linha)
                    <tr wire:key="inad-linha-{{ $linha['ano'] }}">
                        <td>{{ $linha['ano'] }}</td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($linha['valor']) }}</td>
                        <td class="text-right font-bold text-red-600">{{ \App\Support\DinheiroBr::formatar($linha['valor_corrigido']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-6 text-center text-slate-500">Nenhuma taxa em aberto</td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($linhas) > 0)
                <tfoot>
                    <tr class="font-semibold">
                        <th class="text-left">Total</th>
                        <th class="text-right">{{ \App\Support\DinheiroBr::formatar($total) }}</th>
                        <th class="text-right text-red-600">{{ \App\Support\DinheiroBr::formatar($totalCorrigido) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if (count($grade) > 0)
        <div class="card">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Valores corrigidos por mês (IPCA até {{ now()->format('d/m/Y') }})</h3>
            <div class="overflow-x-auto">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Ano</th>
                            @foreach (\App\Support\MesesBr::abreviados() as $mes)
                                <th class="px-2 text-right">{{ $mes }}</th>
                            @endforeach
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grade as $ano => $meses)
                            <tr wire:key="inad-grade-{{ $ano }}">
                                <td class="font-medium">{{ $ano }}</td>
                                @foreach ($meses as $valor)
                                    <td class="px-2 text-right {{ $valor !== null ? 'text-red-600' : '' }}">
                                        {{ $valor !== null ? \App\Support\DinheiroBr::formatar($valor) : '-' }}
                                    </td>
                                @endforeach
                                <td class="text-right font-semibold">
                                    {{ \App\Support\DinheiroBr::formatar(array_sum(array_filter($meses, fn ($v) => $v !== null))) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Memória de Cálculo</h3>
            <div class="space-y-4">
                @foreach ($memoria as $ano => $itens)
                    <div class="rounded-lg border border-slate-200" wire:key="inad-memoria-{{ $ano }}">
                        <button type="button" wire:click="alternarMemoria({{ $ano }})"
                                class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-slate-800 hover:bg-slate-50">
                            <span>Ano {{ $ano }}</span>
                            <span class="text-xs text-slate-500">{{ ($memoriaAberta[$ano] ?? false) ? 'Ocultar' : 'Expandir' }}</span>
                        </button>
                        @if ($memoriaAberta[$ano] ?? false)
                            <div class="border-t border-slate-100 p-4">
                                <table class="table-modern">
                                    <thead>
                                        <tr>
                                            <th>Competência</th>
                                            <th class="text-right">Valor Original</th>
                                            <th class="text-right">IPCA Acumulado (%)</th>
                                            <th>Período</th>
                                            <th class="text-right">Valor Corrigido</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($itens as $item)
                                            <tr>
                                                <td>
                                                    {{ $item['competencia'] }}
                                                    @if (count($item['composicao'] ?? []) > 1)
                                                        <p class="text-xs text-slate-500">{{ implode(' + ', $item['composicao']) }}</p>
                                                    @endif
                                                </td>
                                                <td class="text-right">{{ \App\Support\DinheiroBr::formatar($item['valor_original']) }}</td>
                                                <td class="text-right">{{ number_format($item['ipca_acumulado'], 4, ',', '.') }}</td>
                                                <td>{{ $item['periodo'] }}</td>
                                                <td class="text-right font-semibold">{{ \App\Support\DinheiroBr::formatar($item['valor_corrigido']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-semibold">
                                            <th class="text-left">Total do Ano</th>
                                            <th class="text-right">{{ \App\Support\DinheiroBr::formatar(array_sum(array_column($itens, 'valor_original'))) }}</th>
                                            <th class="text-right">—</th>
                                            <th>—</th>
                                            <th class="text-right">{{ \App\Support\DinheiroBr::formatar(array_sum(array_column($itens, 'valor_corrigido'))) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
