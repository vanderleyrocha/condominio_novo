<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LancamentoFinanceiro;
use App\Models\User;

/**
 * Lançamentos no modelo novo: admin e sindico gerem (level_one aceito até o
 * remap do cutover). A regra de data de corte do legado (DespesaPolicy) foi
 * aposentada com o novo controle de acesso. Sem exclusão (RN-29).
 */
class LancamentoFinanceiroPolicy
{
    public function create(User $user): bool
    {
        return $this->podeGerir($user);
    }

    public function update(User $user, LancamentoFinanceiro $lancamento): bool
    {
        return $this->podeGerir($user);
    }

    private function podeGerir(User $user): bool
    {
        return $user->isAdmin() || $user->isSindico() || $user->isLevelOne();
    }
}
