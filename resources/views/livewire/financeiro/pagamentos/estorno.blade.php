<div class="mx-auto max-w-3xl space-y-6">
    <h1 class="text-xl font-semibold">Estornar Pagamento</h1>

    @if ($erro !== '')
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $erro }}</div>
    @endif

    {{-- Pagamento original --}}
    <div class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Pagamento Original</h2>
        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-700">Data</dt>
                <dd>{{ $pagamento->data?->format('d/m/Y') ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-700">Valor Total</dt>
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
        </dl>
    </div>

    {{-- Mensalidades para estorno --}}
    <form wire:submit="confirmar" class="space-y-6">
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Mensalidades para Estorno</h2>

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
                                <th class="px-4 py-3 text-right">Valor Pago</th>
                                <th class="px-4 py-3 text-right">Valor a Estornar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($pagamento->mensalidades as $mensalidade)
                                <tr class="odd:bg-white even:bg-gray-50">
                                    <td class="px-4 py-2">{{ $mensalidade->ano }}</td>
                                    <td class="px-4 py-2">{{ \App\Support\MesesBr::nome((int) $mensalidade->mes) }}</td>
                                    <td class="px-4 py-2">{{ $mensalidade->vencimento?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right">R$ {{ \App\Support\DinheiroBr::formatar($mensalidade->pivot->valor) }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <input type="number" step="0.01" min="0" max="{{ $mensalidade->pivot->valor }}"
                                               wire:model.live="valores.{{ $mensalidade->id }}"
                                               class="w-28 rounded-md border border-gray-300 px-2 py-1 text-right text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                                        @error("valores.{$mensalidade->id}")
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-4 py-2 font-semibold" colspan="4">Total a Estornar</td>
                                <td class="px-4 py-2 text-right font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($this->totalAEstornar) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('pagamentos.show', $pagamento) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="confirmar">Confirmar Estorno</span>
                <span wire:loading wire:target="confirmar">Estornando...</span>
            </button>
        </div>
    </form>
</div>
