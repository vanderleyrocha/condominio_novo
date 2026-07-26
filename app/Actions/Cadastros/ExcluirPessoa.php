<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Models\Pessoa;
use DomainException;

class ExcluirPessoa
{
    /**
     * Guardas espelham a semântica do legado (RN-28/Q-05) no modelo novo:
     * vínculos com unidades, pagamentos ou conta de acesso bloqueiam.
     * Exclusão é soft delete (deleted_at).
     */
    public function executar(Pessoa $pessoa): void
    {
        if ($pessoa->vinculos()->exists()) {
            throw new DomainException('Não é possível excluir: pessoa possui vínculos com unidades.');
        }

        if ($pessoa->pagamentos()->exists()) {
            throw new DomainException('Não é possível excluir: pessoa possui pagamentos registrados.');
        }

        if ($pessoa->users()->exists()) {
            throw new DomainException('Não é possível excluir: pessoa possui conta de acesso vinculada.');
        }

        $pessoa->delete();
    }
}
