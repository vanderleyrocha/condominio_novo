<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Pagamentos</h1>
        @can('create', \App\Models\Pagamento::class)
            <a href="{{ route('pagamentos.create') }}"
               class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark">
                Novo Pagamento
            </a>
        @endcan
    </div>

    @if ($pagamentos->count() > 0)
        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3">Proprietário</th>
                        <th class="px-4 py-3">Imóvel</th>
                        <th class="px-4 py-3">Descrição</th>
                        <th class="px-4 py-3 text-right">Valor (R$)</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($pagamentos as $pagamento)
                        <tr class="odd:bg-white even:bg-gray-50 hover:bg-gray-100">
                            <td class="px-4 py-2">{{ $pagamento->data?->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $pagamento->proprietario->nome ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $pagamento->imovel->nome ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $pagamento->descricao }}</td>
                            <td class="px-4 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($pagamento->valor) }}</td>
                            <td class="px-4 py-2">
                                @if ($pagamento->estornado)
                                    <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">ESTORNADO</span>
                                @elseif ($pagamento->isEstorno())
                                    <span class="rounded bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700">ESTORNO</span>
                                @else
                                    <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Válido</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('pagamentos.show', $pagamento) }}" title="Detalhes"
                                   class="text-brand hover:underline">Detalhes</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $pagamentos->links() }}</div>
    @else
        <div class="rounded-lg bg-white p-6 shadow">
            <p class="text-sm text-gray-600">Nenhum pagamento registrado.</p>
        </div>
    @endif
</div>
