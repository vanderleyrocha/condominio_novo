<div class="rounded-lg bg-white p-6 shadow">
    <h1 class="mb-4 text-lg font-semibold">Log de acessos</h1>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-brand">
                    <th class="px-3 py-2 font-semibold">Usuário</th>
                    <th class="px-3 py-2 font-semibold">Data</th>
                </tr>
            </thead>
            {{-- DEV-14: agrupamento válido por usuário (o legado aninhava <tr> dentro de <tr>) --}}
            @forelse ($usuarios as $usuario)
                <tbody class="divide-y divide-gray-100 border-t border-gray-200">
                    @forelse ($usuario->accesses as $access)
                        <tr class="hover:bg-gray-50">
                            @if ($loop->first)
                                <td class="px-3 py-2 align-top font-medium" rowspan="{{ $usuario->accesses->count() }}">
                                    {{ $usuario->name }}
                                </td>
                            @endif
                            <td class="px-3 py-2">{{ $access->datetime->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-medium">{{ $usuario->name }}</td>
                            <td class="px-3 py-2 text-gray-500">-</td>
                        </tr>
                    @endforelse
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td colspan="2" class="px-3 py-6 text-center text-gray-500">Nenhum acesso registrado.</td>
                    </tr>
                </tbody>
            @endforelse
        </table>
    </div>
</div>
