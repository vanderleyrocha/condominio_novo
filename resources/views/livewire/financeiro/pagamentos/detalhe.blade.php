<div class="mx-auto max-w-3xl space-y-6">
    <h1 class="page-title">Pagamento #{{ $pagamento->id }}</h1>

    <div class="card">
        <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <p><span class="font-medium text-slate-700">Data:</span> {{ $pagamento->data_pagamento?->format('d/m/Y') ?? '-' }}</p>
            <p><span class="font-medium text-slate-700">Valor:</span> R$ {{ \App\Support\DinheiroBr::formatar($pagamento->valor_total) }}</p>
            <p><span class="font-medium text-slate-700">Pagador:</span> {{ $pagamento->pessoa->nome ?? '-' }}</p>
            <p><span class="font-medium text-slate-700">Unidade:</span> {{ $pagamento->unidade->identificacao ?? '-' }}</p>
            <p><span class="font-medium text-slate-700">Forma:</span> {{ $pagamento->forma_pagamento->rotulo() }}</p>
            <p class="sm:col-span-2"><span class="font-medium text-slate-700">Descrição:</span> {{ $pagamento->descricao }}</p>
            @if ($pagamento->estornoDe !== null)
                <p class="sm:col-span-2 text-red-600">
                    Estorno do pagamento
                    <a href="{{ route('pagamentos.show', $pagamento->estornoDe) }}" class="underline">#{{ $pagamento->estornoDe->id }}</a>
                </p>
            @endif
            @if ($pagamento->estornos->isNotEmpty())
                <p class="sm:col-span-2 text-red-600">
                    Pagamento estornado —
                    @foreach ($pagamento->estornos as $estorno)
                        <a href="{{ route('pagamentos.show', $estorno) }}" class="underline">estorno #{{ $estorno->id }}</a>
                    @endforeach
                </p>
            @endif
        </div>
    </div>

    <div class="card">
        <h2 class="section-label mb-4">Taxas quitadas por este pagamento</h2>
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Vencimento</th>
                    <th class="text-right">Valor aplicado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pagamento->taxasCondominiais as $taxa)
                    <tr wire:key="det-taxa-{{ $taxa->id }}">
                        <td>{{ \App\Support\MesesBr::nome((int) $taxa->competencia_mes) }}/{{ $taxa->competencia_ano }}</td>
                        <td>{{ $taxa->vencimento?->format('d/m/Y') ?? '-' }}</td>
                        <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($taxa->pivot->valor_aplicado) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-4 text-center text-slate-500">Nenhuma taxa vinculada (pagamento avulso).</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($pagamento->taxasCondominiais->isNotEmpty())
                <tfoot>
                    <tr class="font-semibold">
                        <th colspan="2" class="text-left">Total aplicado</th>
                        <th class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($totalAplicado) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="flex items-center gap-3">
        @can('emitirRecibo', $pagamento)
            <a href="{{ route('pdf.pagamentos.recibo', $pagamento) }}" target="_blank" class="btn btn-secondary">Recibo</a>
        @endcan
        @can('estornar', $pagamento)
            @if (! $pagamento->isEstorno() && $pagamento->estornos->isEmpty())
                <a href="{{ route('pagamentos.estorno', $pagamento) }}" class="btn btn-danger">Estornar</a>
            @endif
        @endcan
        <a href="{{ route('pagamentos.index') }}" class="text-sm text-slate-500 hover:underline">Voltar</a>
    </div>
</div>
