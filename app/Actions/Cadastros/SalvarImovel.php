<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Models\Imovel;

/**
 * CRUD de imóveis é novo (BR-HUMANA-002) — o legado geria imóveis direto no banco.
 */
class SalvarImovel
{
    /**
     * @param array{proprietario_id: int, nome: string} $dados
     */
    public function executar(array $dados, ?Imovel $imovel = null): Imovel
    {
        if ($imovel === null) {
            return Imovel::query()->create($dados);
        }

        $imovel->update($dados);

        return $imovel;
    }
}
