<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Models\Unidade;
use DomainException;

class ExcluirUnidade
{
    /**
     * Taxas ou pagamentos vinculados bloqueiam (mesma semântica de ExcluirImovel
     * no legado); vínculos com pessoas são encerrados junto (histórico preservado
     * pelo soft delete da unidade).
     */
    public function executar(Unidade $unidade): void
    {
        if ($unidade->taxasCondominiais()->exists()) {
            throw new DomainException('Não é possível excluir: unidade possui taxas condominiais lançadas.');
        }

        if ($unidade->pagamentos()->exists()) {
            throw new DomainException('Não é possível excluir: unidade possui pagamentos registrados.');
        }

        $unidade->delete();
    }
}
