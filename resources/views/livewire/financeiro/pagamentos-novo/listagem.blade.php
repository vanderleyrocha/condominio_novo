<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Pagamentos</h1>
        @can('create', \App\Models\PagamentoNovo::class)
            <a href="{{ route('pagamentos-novo.create') }}" class="btn btn-primary">Novo pagamento</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="table-modern">
            <thead>
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
            </thead>
            <tbody>
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
                            <a href="{{ route('pagamentos-novo.show', $pagamento) }}" class="table-action">Detalhes</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-6 text-center text-slate-500">Nenhum pagamento registrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $pagamentos->links() }}</div>
</div>
