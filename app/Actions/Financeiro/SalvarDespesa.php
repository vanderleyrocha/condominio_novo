<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Despesa;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class SalvarDespesa
{
    /**
     * @param array{despesa_tipo_id: int, data: string, descricao: string, valor: string, contabilizado?: bool} $dados
     */
    public function executar(array $dados, User $ator, ?Despesa $despesa = null): Despesa
    {
        // Paridade permissions.md §4: level-one só cria/edita despesa com data pós-corte
        $autorizado = $despesa === null
            ? Gate::forUser($ator)->allows('create', [Despesa::class, $dados['data']])
            : Gate::forUser($ator)->allows('update', [$despesa, $dados['data']]);

        if (! $autorizado) {
            throw new AuthorizationException('Sem permissão para registrar despesa nesta data.');
        }

        if (! Gate::forUser($ator)->allows('gerenciarContabilizado', Despesa::class)) {
            $dados['contabilizado'] = true;
        }

        if ($despesa === null) {
            return Despesa::query()->create($dados);
        }

        $despesa->update($dados);

        return $despesa;
    }
}
