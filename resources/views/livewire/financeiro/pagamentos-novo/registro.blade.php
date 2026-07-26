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
                <div>
                    <label for="data" class="label">Data</label>
                    <input id="data" type="date" wire:model="data" class="input">
                    @error('data') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="valor" class="label">Valor do pagamento</label>
                    <input id="valor" type="text" wire:model.live.debounce.500ms="valor" inputmode="decimal" placeholder="0,00" class="input">
                    @error('valor') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="descricao" class="label">Descrição</label>
                    <input id="descricao" type="text" wire:model="descricao" class="input">
                    @error('descricao') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="forma" class="label">Forma de pagamento</label>
                    <select id="forma" wire:model="forma" class="input">
                        @foreach ($formas as $opcao)
                            <option value="{{ $opcao->value }}">{{ $opcao->rotulo() }}</option>
                        @endforeach
                    </select>
                    @error('forma') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pessoaId" class="label">Pagador</label>
                    <select id="pessoaId" wire:model.live="pessoaId" class="input">
                        <option value="">Selecione</option>
                        @foreach ($this->pessoas as $pessoa)
                            <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                        @endforeach
                    </select>
                    @error('pessoaId') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unidadeId" class="label">Unidade</label>
                    <select id="unidadeId" wire:model.live="unidadeId" class="input">
                        <option value="">Selecione</option>
                        @foreach ($this->unidadesDaPessoa as $unidade)
                            <option value="{{ $unidade->id }}">{{ $unidade->identificacao }}</option>
                        @endforeach
                    </select>
                    @error('unidadeId') <p class="error-text">{{ $message }}</p> @enderror
                </div>
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
                <div class="overflow-x-auto">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Ano</th>
                                <th>Mês</th>
                                <th>Vencimento</th>
                                <th class="text-right">Saldo devedor</th>
                                <th class="text-right">Valor a pagar</th>
                            </tr>
                        </thead>
                        <tbody>
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
                        </tbody>
                    </table>
                </div>

                <p class="mt-4 text-right text-sm font-medium text-slate-700">
                    Saldo restante: <span class="font-semibold">R$ {{ \App\Support\DinheiroBr::formatar($alocacao['saldo']) }}</span>
                </p>
            @endif
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">
                <span wire:loading.remove wire:target="salvar">Salvar pagamento</span>
                <span wire:loading wire:target="salvar">Salvando...</span>
            </button>
        </div>
    </form>
</div>
