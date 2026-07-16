<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Receita;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SalvarReceita
{
    /**
     * @param array{data: string, descricao: string, valor: string, contabilizado?: bool, cobranca_extra_id?: ?int} $dados
     */
    public function executar(array $dados, User $ator, ?Receita $receita = null): Receita
    {
        // RN-08: apenas admin controla contabilizado
        if (! Gate::forUser($ator)->allows('gerenciarContabilizado', Receita::class)) {
            $dados['contabilizado'] = true;
        }

        if ($receita === null) {
            return Receita::query()->create($dados);
        }

        $receita->update($dados);

        return $receita;
    }
}
