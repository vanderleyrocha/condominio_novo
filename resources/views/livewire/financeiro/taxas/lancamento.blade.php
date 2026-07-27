<div class="mx-auto max-w-lg space-y-6">
    <h1 class="page-title">Lançar Taxas Condominiais</h1>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    <div class="card">
        <form wire:submit="lancar" class="space-y-4">
            <x-input label="Ano de referência" type="number" wire:model.live="ano" min="2000" max="2100" />

            <x-input label="Valor da taxa condominial" wire:model.live.debounce.400ms="valor" inputmode="decimal" placeholder="0,00"
                     help="Somente a taxa ordinária. Contribuições recorrentes (cobranças extraordinárias com valor por unidade) são somadas como itens próprios." />

            @if ($previa !== [])
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="mb-2 text-sm font-medium text-slate-700">Composição prevista</p>
                    <div class="overflow-x-auto">
                        <table class="table-modern text-sm">
                            <thead>
                                <tr>
                                    <th>Competência</th>
                                    <th>Itens</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($previa as $mes => $linha)
                                    <tr>
                                        <td>{{ \App\Support\MesesBr::nome($mes) }}</td>
                                        <td class="text-xs text-slate-500">
                                            @foreach ($linha['itens'] as $item)
                                                {{ $item['descricao'] }}: {{ \App\Support\DinheiroBr::formatar($item['valor']) }}@if (! $loop->last)<br>@endif
                                            @endforeach
                                        </td>
                                        <td class="text-right font-medium tabular-nums">
                                            R$ {{ \App\Support\DinheiroBr::formatar($linha['total']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Serão lançadas 12 taxas para cada unidade cadastrada, com vencimento no último dia de cada mês.
                    </p>
                </div>
            @endif

            <x-button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove>Salvar</span>
                <span wire:loading>Salvando...</span>
            </x-button>
        </form>
    </div>
</div>
