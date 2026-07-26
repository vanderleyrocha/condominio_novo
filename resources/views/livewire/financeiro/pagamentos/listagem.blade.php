<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Pagamentos</h1>
        @can('create', \App\Models\Pagamento::class)
            <a href="{{ route('pagamentos.create') }}"
               class="btn btn-primary">
                Novo Pagamento
            </a>
        @endcan
    </div>

    @if ($pagamentos->count() > 0)
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Proprietário</th>
                        <th>Imóvel</th>
                        <th>Descrição</th>
                        <th class="text-right">Valor (R$)</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pagamentos as $pagamento)
                        <tr>
                            <td>{{ $pagamento->data?->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ $pagamento->proprietario->nome ?? '-' }}</td>
                            <td>{{ $pagamento->imovel->nome ?? '-' }}</td>
                            <td>{{ $pagamento->descricao }}</td>
                            <td class="text-right">{{ \App\Support\DinheiroBr::formatar($pagamento->valor) }}</td>
                            <td>
                                @if ($pagamento->estornado)
                                    <span class="badge badge-danger">ESTORNADO</span>
                                @elseif ($pagamento->isEstorno())
                                    <span class="badge badge-warning">ESTORNO</span>
                                @else
                                    <span class="badge badge-success">Válido</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('pagamentos.show', $pagamento) }}" title="Detalhes"
                                   class="table-action">Detalhes</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $pagamentos->links() }}</div>
    @else
        <div class="card">
            <p class="text-sm text-slate-600">Nenhum pagamento registrado.</p>
        </div>
    @endif
</div>
