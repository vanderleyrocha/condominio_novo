<div class="space-y-6">
    <div class="card">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <h2 class="text-lg font-semibold text-slate-900">Resumo das receitas e despesas</h2>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="label">De</label>
                    <input type="date" wire:model="de" class="input">
                </div>
                <div>
                    <label class="label">Até</label>
                    <input type="date" wire:model="ate" class="input">
                </div>
                <button type="button" wire:click="$refresh" class="btn btn-primary">Atualizar</button>
                <a href="{{ route('pdf.resumo.intervalo', ['de' => $de, 'ate' => $ate]) }}" target="_blank"
                   class="btn btn-secondary">Download</a>
            </div>
        </div>

        <h3 class="text-xl font-semibold text-slate-900">Receitas</h3>
        <p class="mt-1 text-base">Saldo anterior: <span class="font-bold">{{ \App\Support\DinheiroBr::formatar($saldo) }}</span></p>

        <table class="mt-4 table-modern">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th class="text-right">valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($aplicacoes as $aplicacao)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($aplicacao->data_pagamento)->format('d/m/Y') }}</td>
                        <td>
                            {{ 'Unidade '.$aplicacao->identificacao
                                .' - Taxa '.str_pad((string) $aplicacao->competencia_mes, 2, '0', STR_PAD_LEFT)
                                .'/'.$aplicacao->competencia_ano }}
                        </td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($aplicacao->valor_aplicado) }}</td>
                    </tr>
                @endforeach
                @foreach ($receitas as $receita)
                    <tr wire:key="int-receita-{{ $receita->id }}">
                        <td>{{ $receita->data_lancamento->format('d/m/Y') }}</td>
                        <td>{{ $receita->descricao }}</td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($receita->valor) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <th colspan="2" class="text-left">Total das receitas do período</th>
                    <th class="text-right">{{ \App\Support\DinheiroBr::formatar($totalReceita) }}</th>
                </tr>
            </tfoot>
        </table>

        <hr class="my-6 border-slate-200">

        <h3 class="text-xl font-semibold text-slate-900">Despesas</h3>
        <table class="mt-4 table-modern">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th class="text-right">valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($despesas as $despesa)
                    <tr wire:key="int-despesa-{{ $despesa->id }}">
                        <td>{{ $despesa->data_lancamento->format('d/m/Y') }}</td>
                        <td>{{ $despesa->descricao }}</td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($despesa->valor) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <th colspan="2" class="text-left">Total</th>
                    <th class="text-right">{{ \App\Support\DinheiroBr::formatar($totalDespesa) }}</th>
                </tr>
            </tfoot>
        </table>

        <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 px-6 py-4">
            <span class="text-base">
                {{ 'Saldo em '.date('d/m/Y', strtotime($ate)).': '
                    .\App\Support\DinheiroBr::formatar($saldo).' + '
                    .\App\Support\DinheiroBr::formatar($totalReceita).' - '
                    .\App\Support\DinheiroBr::formatar($totalDespesa).' = '
                    .\App\Support\DinheiroBr::formatar($saldoFinal) }}
            </span>
        </div>
    </div>
</div>
