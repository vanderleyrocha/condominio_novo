<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Models\Imovel;
use DomainException;

class ExcluirImovel
{
    public function executar(Imovel $imovel): void
    {
        if ($imovel->mensalidades()->exists()) {
            throw new DomainException('Não é possível excluir: imóvel possui mensalidades lançadas.');
        }

        if ($imovel->pagamentos()->exists()) {
            throw new DomainException('Não é possível excluir: imóvel possui pagamentos registrados.');
        }

        $imovel->delete();
    }
}
