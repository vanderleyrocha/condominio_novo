<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Models\Condominio;
use App\Models\Unidade;
use RuntimeException;

class SalvarUnidade
{
    /**
     * @param  array{identificacao: string, bloco_id?: ?int, fracao_ideal?: ?string, area?: ?string, vagas_garagem?: int}  $dados
     */
    public function executar(array $dados, ?Unidade $unidade = null): Unidade
    {
        if ($unidade === null) {
            // Enquanto o sistema opera com condomínio único (03-modelo-dados.md),
            // toda unidade nova pertence a ele
            $condominioId = Condominio::query()->value('id')
                ?? throw new RuntimeException('Nenhum condomínio cadastrado — rode migrar:condominios.');

            return Unidade::query()->create($dados + ['condominio_id' => $condominioId]);
        }

        $unidade->update($dados);

        return $unidade;
    }
}
