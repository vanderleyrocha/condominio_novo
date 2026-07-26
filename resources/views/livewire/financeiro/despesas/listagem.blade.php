<div class="space-y-6">
    <h1 class="page-title">Despesas</h1>

    <div class="card">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="label">Ano</label>
                <input type="number" wire:model.live="ano"
                       class="input w-28">
            </div>
            <div class="w-56">
                <label class="label">Tipo</label>
                <select wire:model.live="tipo"
                        class="input">
                    <option value="0">Todos</option>
                    @foreach ($tipos as $id => $descricao)
                        <option value="{{ $id }}">{{ $descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-64 flex-1">
                <label class="label">Pesquisar</label>
                <input type="text" wire:model.live.debounce.400ms="busca"
                       placeholder="Valor, mês (ex.: Janeiro), data (DD/MM/AAAA) ou descrição/tipo"
                       class="input">
            </div>
            @can('create', [App\Models\Despesa::class, now()->toDateString()])
                <button type="button" wire:click="novaDespesa"
                        class="btn btn-primary">Nova despesa</button>
            @endcan
        </div>
        <div class="mt-4 flex flex-wrap items-end gap-4 border-t border-slate-100 pt-4">
            <div>
                <label class="label">Data inicial</label>
                <input type="date" wire:model="dataInicial"
                       class="input">
            </div>
            <div>
                <label class="label">Data final</label>
                <input type="date" wire:model="dataFinal"
                       class="input">
            </div>
            <a href="{{ route('pdf.despesas', ['data_inicial' => $dataInicial, 'data_final' => $dataFinal]) }}"
               target="_blank"
               class="btn btn-primary">Imprimir</a>
        </div>
    </div>

    @if ($mensagem !== '')
        <div class="alert alert-success">{{ $mensagem }}</div>
    @endif
    @if ($erro !== '')
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    @if ($formAberto)
        <div class="card">
            <h3 class="mb-4 text-base font-semibold text-slate-900">
                {{ $despesaId === null ? 'Nova despesa' : 'Editar despesa' }}
            </h3>
            <form wire:submit="salvar" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="label">Tipo</label>
                        <select wire:model="formTipoId"
                                class="input">
                            <option value="">Selecione...</option>
                            @foreach ($tipos as $id => $descricao)
                                <option value="{{ $id }}">{{ $descricao }}</option>
                            @endforeach
                        </select>
                        @error('formTipoId') <p class="error-text text-xs">{{ $message }}</p> @enderror
                        <div class="mt-2 flex gap-2">
                            <input type="text" wire:model="novoTipo" placeholder="Criar novo tipo..."
                                   class="input py-1.5 text-xs">
                            <button type="button" wire:click="criarTipo"
                                    class="btn btn-secondary btn-sm shrink-0">Adicionar</button>
                        </div>
                        @error('novoTipo') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Data</label>
                        <input type="date" wire:model="formData"
                               class="input">
                        @error('formData') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Valor</label>
                        <input type="text" wire:model="formValor" placeholder="0,00"
                               class="input text-right">
                        @error('formValor') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="label">Descrição</label>
                        <input type="text" wire:model="formDescricao"
                               class="input">
                        @error('formDescricao') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    @can('gerenciarContabilizado', App\Models\Despesa::class)
                        <div class="flex items-end pb-2">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="formContabilizado"
                                       class="checkbox">
                                Contabilizada
                            </label>
                        </div>
                    @endcan
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                            class="btn btn-primary">Gravar</button>
                    <button type="button" wire:click="cancelar"
                            class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        <div class="-mx-6 overflow-x-auto px-6">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th class="text-right">Valor</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($despesas as $despesa)
                    @php
                        // Cores de auditoria admin (DEV-T-14/15/16)
                        $classe = '';
                        if (auth()->user()->isAdmin()) {
                            if ($despesa->created_at != $despesa->updated_at) {
                                $classe = $despesa->contabilizado ? 'bg-yellow-100' : 'bg-slate-100';
                            } elseif (! $despesa->contabilizado) {
                                $classe = 'bg-emerald-100';
                            }
                        }
                    @endphp
                    <tr class="{{ $classe }}">
                        <td>{{ $despesas->firstItem() + $loop->index }}</td>
                        <td>{{ $despesa->data->format('d/m/Y') }}</td>
                        <td>
                            {{ $despesa->descricao }}
                            @if ($despesa->tipo)
                                <span class="badge badge-neutral ml-1">{{ $despesa->tipo->descricao }}</span>
                            @endif
                        </td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($despesa->valor) }}</td>
                        <td class="text-right">
                            @if (auth()->user()->isAdmin())
                                <button type="button" wire:click="editar({{ $despesa->id }})"
                                        class="btn btn-primary btn-sm">Editar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-slate-500">Nenhum registro encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <th colspan="3" class="text-left">Total</th>
                    <th class="text-right">{{ \App\Support\DinheiroBr::formatar($total) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        </div>
        <div class="mt-4">{{ $despesas->links() }}</div>
    </div>
</div>
