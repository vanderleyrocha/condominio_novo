<div class="space-y-6">
    <div class="card">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Cobranças Extraordinárias</h2>
            <button type="button" wire:click="novaCobranca" class="btn btn-primary">Nova cobrança</button>
        </div>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-4 text-base font-semibold text-slate-900">
                    {{ $cobrancaId === null ? 'Nova cobrança' : 'Editar cobrança' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="label">Nome</label>
                        <input type="text" wire:model="formNome" class="input">
                        @error('formNome') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Valor total (R$)</label>
                        <input type="text" wire:model="formValor" placeholder="0,00" class="input text-right">
                        @error('formValor') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Método de rateio</label>
                        <select wire:model="formMetodoRateio" class="input">
                            @foreach ($metodos as $metodo)
                                <option value="{{ $metodo->value }}">{{ $metodo->rotulo() }}</option>
                            @endforeach
                        </select>
                        @error('formMetodoRateio') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Vigência início</label>
                        <input type="date" wire:model="formVigenciaInicio" class="input">
                        @error('formVigenciaInicio') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Vigência fim (vazio = permanente)</label>
                        <input type="date" wire:model="formVigenciaFim" class="input">
                        @error('formVigenciaFim') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="label flex items-center gap-2">
                            <input type="checkbox" wire:model="formAtiva" class="checkbox">
                            Ativa
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <button type="submit" class="btn btn-primary">Gravar</button>
                    <button type="button" wire:click="cancelar" class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        @endif

        <div class="-mx-6 overflow-x-auto px-6">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th class="text-right">Valor total</th>
                    <th>Rateio</th>
                    <th>Vigência</th>
                    <th>Ativa</th>
                    <th class="text-right">Apurado (taxas)</th>
                    <th class="text-right">Apurado (receitas)</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cobrancas as $cobranca)
                    <tr wire:key="cobranca-{{ $cobranca->id }}">
                        <td>{{ $cobranca->nome }}</td>
                        <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($cobranca->valor_total) }}</td>
                        <td>{{ $cobranca->metodo_rateio->rotulo() }}</td>
                        <td class="text-sm">
                            {{ $cobranca->vigencia_inicio->format('d/m/Y') }}
                            — {{ $cobranca->vigencia_fim?->format('d/m/Y') ?? 'permanente' }}
                        </td>
                        <td>{{ $cobranca->ativa ? 'Sim' : 'Não' }}</td>
                        <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($cobranca->total_taxas ?? 0) }}</td>
                        <td class="text-right">R$ {{ \App\Support\DinheiroBr::formatar($receitasPorCobranca[$cobranca->id] ?? 0) }}</td>
                        <td class="text-right">
                            <button type="button" wire:click="editar({{ $cobranca->id }})"
                                    class="btn btn-primary btn-sm">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-6 text-center text-slate-500">Nenhuma cobrança extraordinária cadastrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
