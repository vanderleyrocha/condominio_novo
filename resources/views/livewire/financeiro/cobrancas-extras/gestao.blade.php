<div class="space-y-6">
    <div class="card">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Cobranças extras</h2>
            <button type="button" wire:click="novaCobranca"
                    class="btn btn-primary">Nova cobrança</button>
        </div>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-4 text-base font-semibold text-slate-900">
                    {{ $cobrancaId === null ? 'Nova cobrança extra' : 'Editar cobrança extra' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="label">Nome</label>
                        <input type="text" wire:model="formNome"
                               class="input">
                        @error('formNome') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Valor</label>
                        <input type="text" wire:model="formValor" placeholder="0,00"
                               class="input text-right">
                        @error('formValor') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Vigência início</label>
                        <input type="date" wire:model="formVigenciaInicio"
                               class="input">
                        @error('formVigenciaInicio') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Vigência fim (opcional)</label>
                        <input type="date" wire:model="formVigenciaFim"
                               class="input">
                        @error('formVigenciaFim') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="formAtiva"
                               class="checkbox">
                        Ativa
                    </label>
                </div>
                <div class="mt-4 flex gap-3">
                    <button type="submit"
                            class="btn btn-primary">Gravar</button>
                    <button type="button" wire:click="cancelar"
                            class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        @endif

        <div class="-mx-6 overflow-x-auto px-6">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th class="text-right">Valor</th>
                    <th>Vigência</th>
                    <th>Situação</th>
                    <th class="text-right">Total apurado</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cobrancas as $cobranca)
                    @php
                        $apurado = (float) ($cobranca->total_mensalidades ?? 0) + (float) ($cobranca->total_receitas ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $cobranca->nome }}</td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($cobranca->valor) }}</td>
                        <td>
                            {{ $cobranca->vigencia_inicio->format('d/m/Y') }}
                            @if ($cobranca->vigencia_fim)
                                a {{ $cobranca->vigencia_fim->format('d/m/Y') }}
                            @else
                                em diante
                            @endif
                        </td>
                        <td>
                            @if ($cobranca->ativa)
                                <span class="badge badge-success">Ativa</span>
                            @else
                                <span class="badge badge-neutral">Inativa</span>
                            @endif
                        </td>
                        <td class="text-right font-semibold">{{ \App\Support\DinheiroBr::formatar($apurado) }}</td>
                        <td class="text-right">
                            <button type="button" wire:click="editar({{ $cobranca->id }})"
                                    class="btn btn-primary btn-sm">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-slate-500">Nenhuma cobrança extra cadastrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
