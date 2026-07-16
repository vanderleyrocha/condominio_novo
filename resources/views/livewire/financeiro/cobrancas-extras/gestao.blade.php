<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Cobranças extras</h2>
            <button type="button" wire:click="novaCobranca"
                    class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Nova cobrança</button>
        </div>

        @if ($mensagem !== '')
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ $mensagem }}</div>
        @endif

        @if ($formAberto)
            <form wire:submit="salvar" class="mb-6 rounded-md border border-gray-200 p-4">
                <h3 class="mb-4 text-base font-semibold text-gray-900">
                    {{ $cobrancaId === null ? 'Nova cobrança extra' : 'Editar cobrança extra' }}
                </h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome</label>
                        <input type="text" wire:model="formNome"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                        @error('formNome') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valor</label>
                        <input type="text" wire:model="formValor" placeholder="0,00"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-right focus:border-brand focus:ring-brand">
                        @error('formValor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Vigência início</label>
                        <input type="date" wire:model="formVigenciaInicio"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                        @error('formVigenciaInicio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Vigência fim (opcional)</label>
                        <input type="date" wire:model="formVigenciaFim"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                        @error('formVigenciaFim') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="formAtiva"
                               class="rounded border-gray-300 text-brand focus:ring-brand">
                        Ativa
                    </label>
                </div>
                <div class="mt-4 flex gap-3">
                    <button type="submit"
                            class="bg-brand text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-brand-dark">Gravar</button>
                    <button type="button" wire:click="cancelar"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                </div>
            </form>
        @endif

        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2">Nome</th>
                    <th class="px-3 py-2 text-right">Valor</th>
                    <th class="px-3 py-2">Vigência</th>
                    <th class="px-3 py-2">Situação</th>
                    <th class="px-3 py-2 text-right">Total apurado</th>
                    <th class="px-3 py-2 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($cobrancas as $cobranca)
                    @php
                        $apurado = (float) ($cobranca->total_mensalidades ?? 0) + (float) ($cobranca->total_receitas ?? 0);
                    @endphp
                    <tr>
                        <td class="px-3 py-2">{{ $cobranca->nome }}</td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\DinheiroBr::formatar($cobranca->valor) }}</td>
                        <td class="px-3 py-2">
                            {{ $cobranca->vigencia_inicio->format('d/m/Y') }}
                            @if ($cobranca->vigencia_fim)
                                a {{ $cobranca->vigencia_fim->format('d/m/Y') }}
                            @else
                                em diante
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($cobranca->ativa)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Ativa</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Inativa</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right font-semibold">{{ \App\Support\DinheiroBr::formatar($apurado) }}</td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" wire:click="editar({{ $cobranca->id }})"
                                    class="rounded-md bg-brand px-3 py-1 text-xs font-medium text-white hover:bg-brand-dark">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">Nenhuma cobrança extra cadastrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
