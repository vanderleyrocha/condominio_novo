<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Models\UnidadePessoa;
use DomainException;

class EncerrarVinculo
{
    /**
     * Encerra a vigência do vínculo (preserva o histórico de ocupação —
     * 03-modelo-dados.md). Se o vínculo era o responsável financeiro, a
     * unidade fica sem responsável até novo vínculo assumir; o caso é
     * apontado pela validação (migrar:validar-remodelagem).
     */
    public function executar(UnidadePessoa $vinculo, ?string $dataFim = null): void
    {
        if ($vinculo->data_fim !== null) {
            throw new DomainException('Este vínculo já está encerrado.');
        }

        $dataFim ??= now()->toDateString();

        if ($dataFim < $vinculo->data_inicio->toDateString()) {
            throw new DomainException('A data de encerramento não pode ser anterior ao início do vínculo.');
        }

        $vinculo->update([
            'data_fim' => $dataFim,
            'responsavel_financeiro' => false,
        ]);
    }
}
