<div class="mx-auto max-w-3xl space-y-6">
    {{-- Dados do pagamento --}}
    <div class="rounded-lg bg-white p-6 shadow">
        <div class="mb-4 flex items-center gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dados do Pagamento</h2>
            @if ($pagamento->isEstorno())
                <span class="rounded bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700">ESTORNO</span>
            @endif
        </div>

        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-700">Data</dt>
                <dd>{{ $pagamento->data?->format('d/m/Y') ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-700">Valor total</dt>
                <dd>R$ {{ \App\Support\DinheiroBr::formatar($pagamento->valor) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-700">Descrição</dt>
                <dd>{{ $pagamento->descricao }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-700">Proprietário</dt>
                <dd>{{ $pagamento->proprietario->nome ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-700">Imóvel</dt>
                <dd>{{ $pagamento->imovel->nome ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-700">Status</dt>
                <dd class="mt-1">
                    @if ($pagamento->estornado)
                        <span class="inline-block rounded bg-red-100 px-3 py-1 text-red-800">Este pagamento foi estornado.</span>
                    @elseif ($pagamento->isEstorno())
                        <span class="inline-block rounded bg-orange-100 px-3 py-1 text-orange-800">Estorno válido</span>
                    @else
                        <span class="inline-block rounded bg-green-100 px-3 py-1 text-green-800">Pagamento válido</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    {{-- Mensalidades vinculadas --}}
    <div class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Mensalidades Vinculadas</h2>

        @if ($pagamento->mensalidades->isEmpty())
            <p class="text-sm text-gray-600">Nenhuma mensalidade vinculada.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Ano</th>
                            <th class="px-4 py-3">Mês</th>
                            <th class="px-4 py-3">Vencimento</th>
                            <th class="px-4 py-3 text-right">Valor aplicado (R$)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($pagamento->mensalidades as $mensalidade)
                            <tr class="odd:bg-white even:bg-gray-50">
                                <td class="px-4 py-2">{{ $mensalidade->ano }}</td>
                                <td class="px-4 py-2">{{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</td>
                                <td class="px-4 py-2">{{ $mensalidade->vencimento?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($mensalidade->pivot->valor) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td class="px-4 py-2 font-semibold" colspan="3">Total aplicado</td>
                            <td class="px-4 py-2 text-right font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalAplicado) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        @if ($pagamento->isEstorno())
            <div class="mt-4 rounded-md bg-orange-50 p-3 text-sm text-orange-800">
                Este registro refere-se ao estorno do pagamento
                <a href="{{ route('pagamentos.show', $pagamento->pagamento_origem_id) }}" class="font-medium underline">#{{ $pagamento->pagamento_origem_id }}</a>.
            </div>
        @endif
    </div>

    {{-- Estornos vinculados --}}
    @if ($pagamento->estornos->isNotEmpty())
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Estornos Vinculados</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($pagamento->estornos as $estorno)
                    <li class="flex items-center justify-between rounded-md bg-red-50 px-3 py-2">
                        <span>
                            Estorno #{{ $estorno->id }} — {{ $estorno->data?->format('d/m/Y') }} —
                            R$ {{ \App\Support\DinheiroBr::formatar($estorno->valor) }}
                        </span>
                        <a href="{{ route('pagamentos.show', $estorno) }}" class="text-brand hover:underline">Detalhes</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Ações --}}
    <div class="flex flex-wrap items-center gap-3">
        @can('emitirRecibo', $pagamento)
            <a href="{{ route('pdf.pagamentos.recibo', $pagamento) }}" target="_blank"
               class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark">
                Gerar Recibo (PDF)
            </a>
        @endcan

        @can('estornar', $pagamento)
            @if (! $pagamento->estornado && ! $pagamento->isEstorno())
                <a href="{{ route('pagamentos.estorno', $pagamento) }}"
                   class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">
                    Estornar Pagamento
                </a>
            @endif
        @endcan

        <a href="{{ route('pagamentos.index') }}"
           class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            Voltar
        </a>
    </div>
</div>
