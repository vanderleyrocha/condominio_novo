<div class="space-y-6">
    <h1 class="page-title">Receitas</h1>

    <div class="card">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="label">Ano</label>
                <input type="number" wire:model.live="ano"
                       class="input w-28">
            </div>
            <div>
                <label class="label">Mês</label>
                <select wire:model.live="mes"
                        class="input w-full sm:w-36">
                    <option value="">Todos</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-56 flex-1">
                <label class="label">Descrição</label>
                <input type="text" wire:model.live.debounce.400ms="descricao" placeholder="Buscar por descrição..."
                       class="input">
            </div>
            <button type="button" wire:click="$refresh"
                    class="btn btn-primary">Filtrar</button>
            @can('create', App\Models\Receita::class)
                <button type="button" wire:click="novaReceita"
                        class="btn btn-primary">Nova receita</button>
            @endcan
        </div>
    </div>

    @if ($mensagem !== '')
        <div class="alert alert-success">{{ $mensagem }}</div>
    @endif

    @if ($formAberto)
        <div class="card">
            <h3 class="mb-4 text-base font-semibold text-slate-900">
                {{ $receitaId === null ? 'Nova receita' : 'Editar receita' }}
            </h3>
            <form wire:submit="salvar" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="label">Data</label>
                        <input type="date" wire:model="formData"
                               class="input">
                        @error('formData') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Descrição</label>
                        <input type="text" wire:model="formDescricao"
                               class="input">
                        @error('formDescricao') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Valor</label>
                        <input type="text" wire:model="formValor" placeholder="0,00"
                               class="input text-right">
                        @error('formValor') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="label">Cobrança extra (opcional)</label>
                        <select wire:model="formCobrancaExtraId"
                                class="input">
                            <option value="">Nenhuma</option>
                            @foreach ($cobrancasExtras as $cobranca)
                                <option value="{{ $cobranca->id }}">{{ $cobranca->nome }}</option>
                            @endforeach
                        </select>
                        @error('formCobrancaExtraId') <p class="error-text text-xs">{{ $message }}</p> @enderror
                    </div>
                    @can('gerenciarContabilizado', App\Models\Receita::class)
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
                    <th>Data</th>
                    <th>Descrição</th>
                    <th class="text-right">Valor</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receitas as $receita)
                    <tr>
                        <td>{{ $receita->data->format('d/m/Y') }}</td>
                        <td>
                            {{ $receita->descricao }}
                            @if ($receita->cobrancaExtra)
                                <span class="badge badge-info ml-1">{{ $receita->cobrancaExtra->nome }}</span>
                            @endif
                        </td>
                        <td class="text-right">{{ \App\Support\DinheiroBr::formatar($receita->valor) }}</td>
                        <td class="text-right">
                            @can('update', $receita)
                                <button type="button" wire:click="editar({{ $receita->id }})"
                                        class="btn btn-primary btn-sm">Editar</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 text-center text-slate-500">Nenhum registro encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <th colspan="2" class="text-left">Total:</th>
                    <th class="text-right">{{ \App\Support\DinheiroBr::formatar($total) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        </div>
        <div class="mt-4">{{ $receitas->links() }}</div>
    </div>
</div>
