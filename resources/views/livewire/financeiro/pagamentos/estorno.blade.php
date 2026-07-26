<div class="mx-auto max-w-3xl space-y-6">
    <h1 class="page-title">Estornar Pagamento</h1>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    {{-- Pagamento original --}}
    <div class="card">
        <h2 class="section-label mb-4">Pagamento Original</h2>
        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-slate-700">Data</dt>
                <dd>{{ $pagamento->data?->format('d/m/Y') ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-700">Valor Total</dt>
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
        </dl>
    </div>

    {{-- Mensalidades para estorno --}}
    <form wire:submit="confirmar" class="space-y-6">
        <div class="card">
            <h2 class="section-label mb-4">Mensalidades para Estorno</h2>

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
                                <th class="text-right">Valor Pago</th>
                                <th class="text-right">Valor a Estornar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pagamento->mensalidades as $mensalidade)
                                <tr>
                                    <td>{{ $mensalidade->ano }}</td>
                                    <td>{{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</td>
                                    <td>{{ $mensalidade->vencimento?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->pivot->valor) }}</td>
                                    <td class="text-right">
                                        <input type="number" step="0.01" min="0" max="{{ $mensalidade->pivot->valor }}"
                                               wire:model.live="valores.{{ $mensalidade->id }}"
                                               class="input w-28 px-2 py-1 text-right">
                                        @error("valores.{$mensalidade->id}")
                                            <p class="error-text text-xs">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="font-semibold" colspan="4">Total a Estornar</td>
                                <td class="text-right font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($this->totalAEstornar) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('pagamentos.show', $pagamento) }}"
               class="btn btn-secondary">
                Cancelar
            </a>
            <button type="submit" wire:loading.attr="disabled"
                    class="btn btn-danger">
                <span wire:loading.remove wire:target="confirmar">Confirmar Estorno</span>
                <span wire:loading wire:target="confirmar">Estornando...</span>
            </button>
        </div>
    </form>
</div>
