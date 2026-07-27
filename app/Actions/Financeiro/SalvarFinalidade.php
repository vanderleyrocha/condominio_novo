<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\Condominio;
use App\Models\Finalidade;
use RuntimeException;

/**
 * CRUD de finalidades — a destinação da arrecadação
 * (docs/migration/05-plano-composicao-taxas.md §3.1).
 */
class SalvarFinalidade
{
    /**
     * @param  array{nome: string, descricao?: ?string, meta_valor?: ?string, restrita?: bool, vigencia_inicio?: ?string, vigencia_fim?: ?string, ativa?: bool}  $dados
     */
    public function executar(array $dados, ?Finalidade $finalidade = null): Finalidade
    {
        if ($finalidade === null) {
            $condominioId = Condominio::query()->value('id')
                ?? throw new RuntimeException('Nenhum condomínio cadastrado — rode migrar:condominios.');

            return Finalidade::query()->create($dados + ['condominio_id' => $condominioId]);
        }

        $finalidade->update($dados);

        return $finalidade;
    }
}
