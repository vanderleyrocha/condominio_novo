<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\CobrancaExtra;

/**
 * Cobranças extras — generalização decidida em P2 (BR-MIGRAR-011):
 * substitui os hardcodes de "poupança" (R$ 50) e "juros de poupança" do legado.
 */
class SalvarCobrancaExtra
{
    /**
     * @param array{nome: string, valor: string, vigencia_inicio: string, vigencia_fim?: ?string, ativa?: bool} $dados
     */
    public function executar(array $dados, ?CobrancaExtra $cobranca = null): CobrancaExtra
    {
        if ($cobranca === null) {
            return CobrancaExtra::query()->create($dados);
        }

        $cobranca->update($dados);

        return $cobranca;
    }
}
