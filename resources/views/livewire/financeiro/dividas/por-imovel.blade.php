<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-900">
                Dívidas do imóvel {{ $imovel->nome }} - {{ $imovel->proprietario->nome ?? 'Não informado' }} (valores sem correção)
            </h2>
            <a href="{{ route('pdf.dividas.imovel', $imovel) }}" target="_blank"
               class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Download</a>
        </div>

        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2">Ano</th>
                    <th class="px-3 py-2 text-right">Valor original (R$)</th>
                    <th class="px-3 py-2 text-right">Valor corrigido (IPCA)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($linhas as $linha)
                    <tr>
                        <td class="px-3 py-2">{{ $linha['ano'] }}</td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($linha['valor']) }}</td>
                        <td class="px-3 py-2 text-right font-bold text-red-600">{{ \App\Support\DinheiroBr::formatar($linha['valor_corrigido']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-6 text-center text-gray-500">Nenhuma dívida em aberto</td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($linhas) > 0)
                <tfoot>
                    <tr class="font-semibold">
                        <th class="px-3 py-2 text-left">Total</th>
                        <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($total) }}</th>
                        <th class="px-3 py-2 text-right text-red-600">{{ \App\Support\DinheiroBr::formatar($totalCorrigido) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if (count($grade) > 0)
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Valores corrigidos por mês (IPCA até {{ now()->format('d/m/Y') }})</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Ano</th>
                            @foreach (['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'] as $mes)
                                <th class="px-2 py-2 text-right">{{ $mes }}</th>
                            @endforeach
                            <th class="px-3 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($grade as $ano => $meses)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $ano }}</td>
                                @foreach ($meses as $valor)
                                    <td class="px-2 py-2 text-right {{ $valor !== null ? 'text-red-600' : '' }}">
                                        {{ $valor !== null ? \App\Support\DinheiroBr::formatar($valor) : '-' }}
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-right font-semibold">
                                    {{ \App\Support\DinheiroBr::formatar(array_sum(array_filter($meses, fn ($v) => $v !== null))) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Memória de Cálculo</h3>
            <div class="space-y-4">
                @foreach ($memoria as $ano => $itens)
                    <div class="rounded-md border border-gray-200">
                        <button type="button" wire:click="alternarMemoria({{ $ano }})"
                                class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-gray-800 hover:bg-gray-50">
                            <span>Ano {{ $ano }}</span>
                            <span class="text-xs text-gray-500">{{ ($memoriaAberta[$ano] ?? false) ? 'Ocultar' : 'Expandir' }}</span>
                        </button>
                        @if ($memoriaAberta[$ano] ?? false)
                            <div class="border-t border-gray-100 p-4">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead>
                                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            <th class="px-3 py-2">Competência</th>
                                            <th class="px-3 py-2 text-right">Valor Original</th>
                                            <th class="px-3 py-2 text-right">IPCA Acumulado (%)</th>
                                            <th class="px-3 py-2">Período</th>
                                            <th class="px-3 py-2 text-right">Valor Corrigido</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($itens as $item)
                                            <tr>
                                                <td class="px-3 py-2">{{ $item['competencia'] }}</td>
                                                <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($item['valor_original']) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number_format($item['ipca_acumulado'], 4, ',', '.') }}</td>
                                                <td class="px-3 py-2">{{ $item['periodo'] }}</td>
                                                <td class="px-3 py-2 text-right font-semibold">{{ \App\Support\DinheiroBr::formatar($item['valor_corrigido']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-semibold">
                                            <th class="px-3 py-2 text-left">Total do Ano</th>
                                            <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar(array_sum(array_column($itens, 'valor_original'))) }}</th>
                                            <th class="px-3 py-2 text-right">—</th>
                                            <th class="px-3 py-2">—</th>
                                            <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar(array_sum(array_column($itens, 'valor_corrigido'))) }}</th>
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
