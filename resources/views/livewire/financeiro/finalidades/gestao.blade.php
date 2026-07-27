<div class="space-y-6">
    <div class="card">
        <x-page-header title="Finalidades" class="mb-4">
            @can('create', \App\Models\Finalidade::class)
                <x-button wire:click="nova">Nova finalidade</x-button>
            @endcan
        </x-page-header>

        <p class="mb-4 text-sm text-slate-500">
            A finalidade diz <strong>para que serve</strong> o dinheiro que entra — vale para itens da taxa
            condominial e para lançamentos financeiros. O arrecadado soma as duas fontes.
        </p>

        @if ($mensagem !== '')
            <div class="alert alert-success mb-4">{{ $mensagem }}</div>
        @endif

        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="mb-4 text-base font-semibold text-slate-900">
                    {{ $finalidadeId === null ? 'Nova finalidade' : 'Editar finalidade' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <x-input label="Nome" wire:model="formNome" placeholder="Ex.: Pintura do prédio" />
                    <x-input label="Meta de arrecadação (R$)" wire:model="formMeta" placeholder="0,00" class="text-right"
                             help="Opcional — orçamento previsto." />
                    <div class="flex items-end pb-2">
                        <label class="label flex items-center gap-2">
                            <input type="checkbox" wire:model="formAtiva" class="checkbox">
                            Ativa
                        </label>
                    </div>
                    <x-input label="Vigência início (vazio = sempre)" type="date" wire:model="formVigenciaInicio" />
                    <x-input label="Vigência fim (vazio = permanente)" type="date" wire:model="formVigenciaFim" />
                    <div class="md:col-span-3">
                        <x-input label="Descrição" wire:model="formDescricao"
                                 placeholder="Ex.: Arrecadação destinada a viabilizar a pintura do prédio." />
                    </div>
                    <div class="md:col-span-3">
                        <label class="label flex items-start gap-2">
                            <input type="checkbox" wire:model="formRestrita" class="checkbox mt-0.5">
                            <span>
                                Recursos vinculados
                                <span class="block text-xs font-normal text-slate-500">
                                    O saldo desta finalidade é dinheiro carimbado: sai do
                                    <strong>disponível para custeio ordinário</strong> no Resumo financeiro e
                                    aparece destacado. Deixe desmarcado para o custeio corrente.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <x-button type="submit">Gravar</x-button>
                    <x-button variant="secondary" wire:click="cancelar">Cancelar</x-button>
                </div>
            </form>
        @endif

        <x-table class="-mx-6 px-6">
            <x-slot:head>
                <tr>
                    <th>Nome</th>
                    <th>Vigência</th>
                    <th>Ativa</th>
                    <th class="text-right">Cobrado em taxas</th>
                    <th class="text-right">Arrecadado</th>
                    <th class="text-right">Saldo do fundo</th>
                    <th class="text-right">A receber</th>
                    <th class="text-right">Meta</th>
                    <th class="text-right">Ações</th>
                </tr>
            </x-slot:head>
            @forelse ($finalidades as $finalidade)
                @php($linha = $arrecadado[$finalidade->id] ?? null)
                <tr wire:key="finalidade-{{ $finalidade->id }}">
                    <td>
                        {{ $finalidade->nome }}
                        @if ($finalidade->restrita)
                            <span class="badge badge-warning">Vinculada</span>
                        @endif
                        @if ($finalidade->descricao)
                            <p class="text-xs text-slate-500">{{ $finalidade->descricao }}</p>
                        @endif
                    </td>
                    <td class="text-sm">
                        {{ $finalidade->vigencia_inicio?->format('d/m/Y') ?? 'sempre' }}
                        — {{ $finalidade->vigencia_fim?->format('d/m/Y') ?? 'permanente' }}
                    </td>
                    <td>{{ $finalidade->ativa ? 'Sim' : 'Não' }}</td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['cobrado'] ?? '0.00') }}</td>
                    <td class="text-right font-semibold tabular-nums text-emerald-600">
                        R$ {{ \App\Support\DinheiroBr::formatar($linha['arrecadado'] ?? '0.00') }}
                    </td>
                    <td class="text-right tabular-nums" title="Arrecadado menos as despesas lançadas nesta finalidade">
                        R$ {{ \App\Support\DinheiroBr::formatar($linha['saldo'] ?? '0.00') }}
                    </td>
                    <td class="text-right tabular-nums">R$ {{ \App\Support\DinheiroBr::formatar($linha['a_receber'] ?? '0.00') }}</td>
                    <td class="text-right tabular-nums">
                        {{ $finalidade->meta_valor === null ? '—' : 'R$ '.\App\Support\DinheiroBr::formatar($finalidade->meta_valor) }}
                    </td>
                    <td class="text-right">
                        @can('update', $finalidade)
                            <x-table-action wire:click="editar({{ $finalidade->id }})">Editar</x-table-action>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="py-6 text-center text-slate-500">Nenhuma finalidade cadastrada.</td>
                </tr>
            @endforelse
        </x-table>
    </div>
</div>
