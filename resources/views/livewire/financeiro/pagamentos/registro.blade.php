<div class="mx-auto max-w-4xl space-y-6">
    <h1 class="page-title">Novo Pagamento</h1>

    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    <form wire:submit="salvar" class="space-y-6">
        {{-- Dados do pagamento --}}
        <div class="card">
            <h2 class="section-label mb-4">Dados do Pagamento</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input label="Data" type="date" wire:model="data" />
                <x-input label="Valor do pagamento" name="valor" wire:model.live.debounce.500ms="valor"
                         inputmode="decimal" placeholder="0,00" />
                <x-input label="Descrição" wire:model="descricao" />
                <x-select label="Forma de pagamento" name="forma" wire:model="forma">
                    @foreach ($formas as $opcao)
                        <option value="{{ $opcao->value }}">{{ $opcao->rotulo() }}</option>
                    @endforeach
                </x-select>
                <x-select label="Pagador" wire:model.live="pessoaId">
                    <option value="">Selecione</option>
                    @foreach ($this->pessoas as $pessoa)
                        <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                    @endforeach
                </x-select>
                <x-select label="Unidade" wire:model.live="unidadeId">
                    <option value="">Selecione</option>
                    @foreach ($this->unidadesDaPessoa as $unidade)
                        <option value="{{ $unidade->id }}">{{ $unidade->identificacao }}</option>
                    @endforeach
                </x-select>
            </div>
        </div>

        {{-- Filtro por ano --}}
        <div class="card">
            <h2 class="section-label mb-4">Filtro por Ano</h2>
            <label class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" wire:model.live="todosAnos" class="checkbox">
                Selecionar todos os anos
            </label>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                @foreach ($this->anosDisponiveis() as $anoDisponivel)
                    <label class="flex items-center gap-1.5 text-sm text-slate-600">
                        <input type="checkbox" wire:model.live="anos" value="{{ $anoDisponivel }}" class="checkbox">
                        {{ $anoDisponivel }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Taxas em aberto --}}
        <div class="card">
            <h2 class="section-label mb-4">Taxas em Aberto</h2>

            @if (empty($anos))
                <p class="text-sm text-slate-600">Selecione ao menos um ano para visualizar as taxas.</p>
            @elseif ($this->taxasEmAberto->isEmpty())
                <p class="text-sm text-slate-600">Nenhuma taxa em aberto encontrada.</p>
            @else
                @php $alocacao = $this->alocacao; @endphp
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th></th>
                            <th>Ano</th>
                            <th>Mês</th>
                            <th>Vencimento</th>
                            <th class="text-right">Saldo devedor</th>
                            <th class="text-right">Valor a pagar</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($this->taxasEmAberto as $taxa)
                        @php
                            $aplicado = $alocacao['linhas'][$taxa->id] ?? null;
                            $devido = (float) $taxa->valorDevido() - (float) ($taxa->valor_pago ?? 0);
                            $classeLinha = '';
                            if ($aplicado !== null && $aplicado > 0) {
                                $classeLinha = $aplicado < $devido ? 'bg-status-parcial' : 'bg-teal-50';
                            }
                        @endphp
                        <tr class="{{ $classeLinha }}" wire:key="pg-taxa-{{ $taxa->id }}">
                            <td>
                                <input type="checkbox" wire:model.live="selecionadas" value="{{ $taxa->id }}" class="checkbox">
                            </td>
                            <td>{{ $taxa->competencia_ano }}</td>
                            <td>{{ \App\Support\MesesBr::nome((int) $taxa->competencia_mes) }}</td>
                            <td>{{ $taxa->vencimento?->format('d/m/Y') ?? '-' }}</td>
                            <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($devido) }}</td>
                            <td class="text-right">
                                {{ $aplicado !== null ? 'R$ '.\App\Support\DinheiroBr::formatar($aplicado) : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </x-table>

                <p class="mt-4 text-right text-sm font-medium text-slate-700">
                    Saldo restante: <span class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($alocacao['saldo']) }}</span>
                </p>
            @endif
        </div>

        <div class="flex justify-end">
            <x-button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="salvar">Salvar pagamento</span>
                <span wire:loading wire:target="salvar">Salvando...</span>
            </x-button>
        </div>
    </form>
</div>
