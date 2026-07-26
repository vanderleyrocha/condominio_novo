<div class="card">
    <h1 class="page-title mb-4">Log de acessos</h1>

    <div class="overflow-x-auto">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Data</th>
                </tr>
            </thead>
            {{-- DEV-14: agrupamento válido por usuário (o legado aninhava <tr> dentro de <tr>) --}}
            @forelse ($usuarios as $usuario)
                <tbody class="divide-y divide-slate-100 border-t border-slate-200">
                    @forelse ($usuario->accesses as $access)
                        <tr>
                            @if ($loop->first)
                                <td class="align-top font-medium" rowspan="{{ $usuario->accesses->count() }}">
                                    {{ $usuario->name }}
                                </td>
                            @endif
                            <td>{{ $access->datetime->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="font-medium">{{ $usuario->name }}</td>
                            <td class="text-slate-500">-</td>
                        </tr>
                    @endforelse
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td colspan="2" class="py-6 text-center text-slate-500">Nenhum acesso registrado.</td>
                    </tr>
                </tbody>
            @endforelse
        </table>
    </div>
</div>
