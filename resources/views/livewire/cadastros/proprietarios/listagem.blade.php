<div class="rounded-lg bg-white p-6 shadow">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Proprietários Cadastrados</h1>
        <a href="{{ route('proprietarios.create') }}"
           class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-dark">
            Novo Proprietário
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-brand">
                    <th class="px-3 py-2 font-semibold">Nome</th>
                    <th class="px-3 py-2 font-semibold">CPF</th>
                    <th class="px-3 py-2 font-semibold">Telefone</th>
                    <th class="px-3 py-2 font-semibold">Inquilino</th>
                    <th class="px-3 py-2 text-right font-semibold">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($proprietarios as $proprietario)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2">{{ $proprietario->nome }}</td>
                        <td class="px-3 py-2">{{ $this->formatarCpf($proprietario->cpf) }}</td>
                        <td class="px-3 py-2">{{ $proprietario->telefone }}</td>
                        <td class="px-3 py-2">
                            @if ($proprietario->nome_inquilino)
                                {{ $proprietario->nome_inquilino }}
                                @if ($proprietario->telefone_inquilino)
                                    <small class="block text-gray-500">{{ $proprietario->telefone_inquilino }}</small>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('proprietarios.edit', $proprietario) }}" title="Editar"
                               class="inline-block rounded-md px-2 py-1 text-brand hover:bg-blue-50">
                                Editar
                            </a>
                            <button type="button" wire:click="confirmarExclusao({{ $proprietario->id }})" title="Excluir"
                                    class="inline-block rounded-md px-2 py-1 text-red-600 hover:bg-red-50">
                                Excluir
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">Nenhum proprietário encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $proprietarios->links() }}
    </div>

    {{-- Modal de confirmação de exclusão (DEV-13) --}}
    @if ($confirmandoExclusaoId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:keydown.escape.window="cancelarExclusao">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow">
                <p class="mb-6 text-sm text-gray-700">Tem certeza que deseja excluir este proprietário?</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelarExclusao"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="excluir" wire:loading.attr="disabled"
                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
