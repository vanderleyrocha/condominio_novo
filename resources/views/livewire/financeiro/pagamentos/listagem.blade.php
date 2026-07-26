<div class="space-y-6">
    <x-page-header title="Pagamentos">
        @can('create', \App\Models\Pagamento::class)
            <x-button :href="route('pagamentos.create')">Novo pagamento</x-button>
        @endcan
    </x-page-header>

    <x-table class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <x-slot:head>
            <tr>
                <th>Data</th>
                <th>Pagador</th>
                <th>Unidade</th>
                <th>Descrição</th>
                <th>Forma</th>
                <th class="text-right">Valor</th>
                <th>Situação</th>
                <th class="text-right">Ações</th>
            </tr>
        </x-slot:head>
        @forelse ($pagamentos as $pagamento)
            <tr wire:key="pg-{{ $pagamento->id }}" @class(['text-red-600' => $pagamento->isEstorno()])>
                <td>{{ $pagamento->data_pagamento?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $pagamento->pessoa->nome ?? '-' }}</td>
                <td>{{ $pagamento->unidade->identificacao ?? '-' }}</td>
                <td>{{ $pagamento->descricao }}</td>
                <td>{{ $pagamento->forma_pagamento->rotulo() }}</td>
                <td class="text-right font-medium">R$ {{ \App\Support\DinheiroBr::formatar($pagamento->valor_total) }}</td>
                <td>
                    @if ($pagamento->isEstorno())
                        Estorno
                    @elseif ($pagamento->estornos->isNotEmpty())
                        Estornado
                    @else
                        Normal
                    @endif
                </td>
                <td class="text-right">
                    <x-table-action :href="route('pagamentos.show', $pagamento)">Detalhes</x-table-action>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="py-6 text-center text-slate-500">Nenhum pagamento registrado.</td>
            </tr>
        @endforelse
    </x-table>

    <div>{{ $pagamentos->links() }}</div>
</div>
