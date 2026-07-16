<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900">Dashboard</h2>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo do mês corrente</p>
            <p class="mt-2 text-2xl font-bold {{ $saldoMes < 0 ? 'text-red-600' : 'text-gray-900' }}">
                R$ {{ \App\Support\DinheiroBr::formatar($saldoMes) }}
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Saldo anterior: R$ {{ \App\Support\DinheiroBr::formatar($saldoAnterior) }} |
                Movimento do mês: R$ {{ \App\Support\DinheiroBr::formatar($movimentoMes) }}
            </p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Mensalidades em aberto</p>
            <p class="mt-2 text-2xl font-bold text-red-600">{{ $emAbertoQuantidade }}</p>
            <p class="mt-1 text-xs text-gray-500">Total devido: R$ {{ \App\Support\DinheiroBr::formatar($emAbertoTotal) }}</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Última receita</p>
            @if ($ultimaReceita)
                <p class="mt-2 text-2xl font-bold text-green-600">R$ {{ \App\Support\DinheiroBr::formatar($ultimaReceita->valor) }}</p>
                <p class="mt-1 truncate text-xs text-gray-500">{{ $ultimaReceita->data->format('d/m/Y') }} — {{ $ultimaReceita->descricao }}</p>
            @else
                <p class="mt-2 text-sm text-gray-500">Nenhum registro encontrado.</p>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Última despesa</p>
            @if ($ultimaDespesa)
                <p class="mt-2 text-2xl font-bold text-red-600">R$ {{ \App\Support\DinheiroBr::formatar($ultimaDespesa->valor) }}</p>
                <p class="mt-1 truncate text-xs text-gray-500">{{ $ultimaDespesa->data->format('d/m/Y') }} — {{ $ultimaDespesa->descricao }}</p>
            @else
                <p class="mt-2 text-sm text-gray-500">Nenhum registro encontrado.</p>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Últimos pagamentos</h3>
            <a href="{{ route('pagamentos.index') }}" class="text-sm text-brand hover:underline">Ver todos</a>
        </div>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Proprietário</th>
                    <th class="px-3 py-2">Imóvel</th>
                    <th class="px-3 py-2">Descrição</th>
                    <th class="px-3 py-2 text-right">Valor (R$)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($ultimosPagamentos as $pagamento)
                    <tr>
                        <td class="px-3 py-2">{{ $pagamento->data->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $pagamento->proprietario->nome ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $pagamento->imovel->nome ?? '-' }}</td>
                        <td class="px-3 py-2">
                            {{ $pagamento->descricao }}
                            @if ($pagamento->isEstorno())
                                <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">ESTORNO</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($pagamento->valor) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">Nenhum pagamento registrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
