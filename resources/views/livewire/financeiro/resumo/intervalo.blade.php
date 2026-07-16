<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <h2 class="text-lg font-semibold text-gray-900">Resumo das receitas e despesas</h2>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">De</label>
                    <input type="date" wire:model="de"
                           class="mt-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Até</label>
                    <input type="date" wire:model="ate"
                           class="mt-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                </div>
                <button type="button" wire:click="$refresh"
                        class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Atualizar</button>
                <a href="{{ route('pdf.resumo.intervalo', ['de' => $de, 'ate' => $ate]) }}" target="_blank"
                   class="rounded-md bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Download</a>
            </div>
        </div>

        <h3 class="text-xl font-semibold text-gray-900">Receitas</h3>
        <p class="mt-1 text-base">Saldo anterior: <span class="font-bold">{{ \App\Support\DinheiroBr::formatar($saldo) }}</span></p>

        <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Descrição</th>
                    <th class="px-3 py-2 text-right">valor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($mensalidades as $mensalidade)
                    <tr>
                        <td class="px-3 py-2">{{ $mensalidade->pago_em->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            {{ 'Apartamento '.$mensalidade->imovel->nome
                                .', Proprietário '.($mensalidade->imovel->proprietario->nome ?? 'Não informado')
                                .' - Mensalidade '.str_pad((string) $mensalidade->mes, 2, '0', STR_PAD_LEFT)
                                .'/'.$mensalidade->ano }}
                        </td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($mensalidade->valor_pago) }}</td>
                    </tr>
                @endforeach
                @foreach ($receitas as $receita)
                    <tr>
                        <td class="px-3 py-2">{{ $receita->data->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $receita->descricao }}</td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($receita->valor) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <th colspan="2" class="px-3 py-2 text-left">Total das receitas do período</th>
                    <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($totalReceita) }}</th>
                </tr>
            </tfoot>
        </table>

        <hr class="my-6 border-gray-200">

        <h3 class="text-xl font-semibold text-gray-900">Despesas</h3>
        <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Descrição</th>
                    <th class="px-3 py-2 text-right">valor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($despesas as $despesa)
                    <tr>
                        <td class="px-3 py-2">{{ $despesa->data->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $despesa->descricao }}</td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($despesa->valor) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <th colspan="2" class="px-3 py-2 text-left">Total</th>
                    <th class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($totalDespesa) }}</th>
                </tr>
            </tfoot>
        </table>

        <div class="mt-6 rounded-full border border-gray-200 bg-gray-50 px-6 py-4">
            <span class="text-base">
                {{ 'Saldo em '.date('d/m/Y', strtotime($ate)).': '
                    .\App\Support\DinheiroBr::formatar($saldo).' + '
                    .\App\Support\DinheiroBr::formatar($totalReceita).' - '
                    .\App\Support\DinheiroBr::formatar($totalDespesa).' = '
                    .\App\Support\DinheiroBr::formatar($saldoFinal) }}
            </span>
        </div>
    </div>
</div>
