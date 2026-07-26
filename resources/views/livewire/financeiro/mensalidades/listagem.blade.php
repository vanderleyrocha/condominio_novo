<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Mensalidades</h1>
        <a href="{{ route('mensalidades.grade', $ano) }}"
           class="btn btn-primary">
            Grade anual
        </a>
    </div>

    {{-- Filtro --}}
    <div class="card">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="ano" class="label">Ano</label>
                <input id="ano" type="number" min="2020" max="2030" wire:model.live="ano"
                       class="input w-32">
            </div>
            <div>
                <label for="imovelId" class="label">Imóvel</label>
                <select id="imovelId" wire:model.live="imovelId"
                        class="input w-full sm:w-72">
                    <option value="">Selecione um imóvel</option>
                    @foreach ($imoveis as $imovel)
                        <option value="{{ $imovel->id }}">Ap {{ $imovel->nome }} - {{ $imovel->proprietario->nome ?? 'Não informado' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($imovelSelecionado !== null)
        {{-- Painel do imóvel --}}
        <div class="card">
            <h2 class="section-label mb-3">Informações do Imóvel</h2>
            <div class="flex flex-wrap gap-8 text-sm">
                <p><span class="font-medium text-slate-700">Imóvel:</span> {{ $imovelSelecionado->nome }}</p>
                <p><span class="font-medium text-slate-700">Proprietário:</span> {{ $imovelSelecionado->proprietario->nome ?? 'Não informado' }}</p>
            </div>
        </div>

        @if ($mensalidades !== null && $mensalidades->count() > 0)
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Mês</th>
                            <th>Vencimento</th>
                            <th>Valor</th>
                            <th>Acréscimo</th>
                            <th>Desconto</th>
                            <th>Valor Pago</th>
                            <th>Data do Pagamento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mensalidades as $mensalidade)
                            @php
                                $status = $mensalidade->status();
                                $corValorPago = match ($status) {
                                    \App\Enums\StatusMensalidade::Paga => 'text-emerald-600',
                                    \App\Enums\StatusMensalidade::PagaParcial => 'text-amber-600',
                                    default => 'text-red-600',
                                };
                            @endphp
                            <tr>
                                <td>{{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</td>
                                <td>{{ $mensalidade->vencimento?->format('d/m/Y') ?? '-' }}</td>
                                <td>R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->valor) }}</td>
                                <td>R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->acrescimo) }}</td>
                                <td>R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->desconto) }}</td>
                                <td class="font-medium {{ $corValorPago }}">R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->valor_pago) }}</td>
                                <td>{{ $mensalidade->pago_em?->format('d/m/Y') ?? '-' }}</td>
                                <td>
                                    <div class="flex gap-2">
                                        @can('update', $mensalidade)
                                            <a href="{{ route('mensalidades.edit', $mensalidade) }}" title="Editar mensalidade"
                                               class="table-action">Editar</a>
                                        @endcan
                                        @if ((float) $mensalidade->valor_pago > 0)
                                            <a href="{{ route('pdf.mensalidades.recibo', $mensalidade) }}" target="_blank" title="Ver recibo"
                                               class="table-action-muted">Recibo</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="font-semibold" colspan="2">Totais</td>
                            <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalValor) }}</td>
                            <td>-</td>
                            <td>-</td>
                            <td class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($totalValorPago) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div>{{ $mensalidades->links() }}</div>
        @else
            <div class="card py-10 text-center">
                <p class="mb-4 text-sm text-slate-600">Nenhuma mensalidade encontrada para este imóvel no ano de {{ $ano }}</p>
                @can('lancar', \App\Models\Mensalidade::class)
                    <a href="{{ route('mensalidades.lancar') }}"
                       class="btn btn-primary">
                        Lançar Mensalidades
                    </a>
                @endcan
            </div>
        @endif
    @else
        <div class="card">
            <p class="text-sm text-slate-600">Selecione um imóvel para visualizar as mensalidades</p>
        </div>
    @endif
</div>
