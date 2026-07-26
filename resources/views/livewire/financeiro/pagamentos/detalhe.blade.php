<div class="mx-auto max-w-3xl space-y-6">
    {{-- Dados do pagamento --}}
    <div class="card">
        <div class="mb-4 flex items-center gap-3">
            <h2 class="section-label">Dados do Pagamento</h2>
            @if ($pagamento->isEstorno())
                <span class="badge badge-warning">ESTORNO</span>
            @endif
        </div>

        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-slate-700">Data</dt>
                <dd>{{ $pagamento->data?->format('d/m/Y') ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-700">Valor total</dt>
                <dd>R$ {{ \App\Support\DinheiroBr::formatar($pagamento->valor) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-700">Descrição</dt>
                <dd>{{ $pagamento->descricao }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-700">Proprietário</dt>
                <dd>{{ $pagamento->proprietario->nome ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-700">Imóvel</dt>
                <dd>{{ $pagamento->imovel->nome ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-700">Status</dt>
                <dd class="mt-1">
                    @if ($pagamento->estornado)
                        <span class="badge badge-danger">Este pagamento foi estornado.</span>
                    @elseif ($pagamento->isEstorno())
                        <span class="badge badge-warning">Estorno válido</span>
                    @else
                        <span class="badge badge-success">Pagamento válido</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    {{-- Mensalidades vinculadas --}}
    <div class="card">
        <h2 class="section-label mb-4">Mensalidades Vinculadas</h2>

        @if ($pagamento->mensalidades->isEmpty())
            <p class="text-sm text-slate-600">Nenhuma mensalidade vinculada.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Ano</th>
                            <th>Mês</th>
                            <th>Vencimento</th>
                            <th class="text-right">Valor aplicado (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pagamento->mensalidades as $mensalidade)
                            <tr>
                                <td>{{ $mensalidade->ano }}</td>
                                <td>{{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</td>
                                <td>{{ $mensalidade->vencimento?->format('d/m/Y') ?? '-' }}</td>
                                <td class="text-right">{{ \App\Support\DinheiroBr::formatar($mensalidade->pivot->valor) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="font-semibold" colspan="3">Total aplicado</td>
                            <td class="text-right font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalAplicado) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        @if ($pagamento->isEstorno())
            <div class="alert alert-warning mt-4">
                Este registro refere-se ao estorno do pagamento
                <a href="{{ route('pagamentos.show', $pagamento->pagamento_origem_id) }}" class="font-medium underline">#{{ $pagamento->pagamento_origem_id }}</a>.
            </div>
        @endif
    </div>

    {{-- Estornos vinculados --}}
    @if ($pagamento->estornos->isNotEmpty())
        <div class="card">
            <h2 class="section-label mb-4">Estornos Vinculados</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($pagamento->estornos as $estorno)
                    <li class="flex items-center justify-between rounded-lg bg-red-50 px-3 py-2">
                        <span>
                            Estorno #{{ $estorno->id }} — {{ $estorno->data?->format('d/m/Y') }} —
                            R$ {{ \App\Support\DinheiroBr::formatar($estorno->valor) }}
                        </span>
                        <a href="{{ route('pagamentos.show', $estorno) }}" class="table-action">Detalhes</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Ações --}}
    <div class="flex flex-wrap items-center gap-3">
        @can('emitirRecibo', $pagamento)
            <a href="{{ route('pdf.pagamentos.recibo', $pagamento) }}" target="_blank"
               class="btn btn-primary">
                Gerar Recibo (PDF)
            </a>
        @endcan

        @can('estornar', $pagamento)
            @if (! $pagamento->estornado && ! $pagamento->isEstorno())
                <a href="{{ route('pagamentos.estorno', $pagamento) }}"
                   class="btn btn-danger">
                    Estornar Pagamento
                </a>
            @endif
        @endcan

        <a href="{{ route('pagamentos.index') }}"
           class="btn btn-secondary">
            Voltar
        </a>
    </div>
</div>
