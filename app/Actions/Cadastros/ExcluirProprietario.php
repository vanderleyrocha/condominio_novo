<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Models\Proprietario;
use DomainException;

class ExcluirProprietario
{
    public function executar(Proprietario $proprietario): void
    {
        // RN-28 — mensagem de negócio preservada do legado
        if ($proprietario->imoveis()->exists()) {
            throw new DomainException('Não é possível excluir: proprietário possui imóveis vinculados.');
        }

        // Guarda estendida (decisão Q-05): pagamentos vinculados também bloqueiam
        if ($proprietario->pagamentos()->exists()) {
            throw new DomainException('Não é possível excluir: proprietário possui pagamentos registrados.');
        }

        $proprietario->delete();
    }
}
