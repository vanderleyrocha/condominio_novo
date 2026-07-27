<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\ItemTaxa;
use App\Models\TaxaCondominial;
use App\Models\User;
use App\Services\ComposicaoTaxaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Cria ou atualiza um item da composição da mensalidade (Etapa 4 de
 * docs/migration/05-plano-composicao-taxas.md). É por aqui que o valor devido
 * da taxa muda — `valor_original` é recalculado pelo ComposicaoTaxaService,
 * junto com o status.
 */
class SalvarItemTaxa
{
    public function __construct(private readonly ComposicaoTaxaService $composicaoService) {}

    /**
     * @param  array{plano_conta_id: int, descricao: string, valor: string, finalidade_id?: ?int, ordem?: int}  $dados
     */
    public function executar(TaxaCondominial $taxa, array $dados, User $ator, ?ItemTaxa $item = null): ItemTaxa
    {
        $permitido = $item === null
            ? Gate::forUser($ator)->allows('create', ItemTaxa::class)
            : Gate::forUser($ator)->allows('update', $item);

        if (! $permitido) {
            throw new AuthorizationException('Sem permissão para alterar a composição da taxa.');
        }

        return $item === null
            ? $this->composicaoService->adicionarItem($taxa, $dados)
            : $this->composicaoService->atualizarItem($item, $dados);
    }
}
