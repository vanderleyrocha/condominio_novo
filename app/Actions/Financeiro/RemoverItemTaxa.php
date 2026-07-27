<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\ItemTaxa;
use App\Models\User;
use App\Services\ComposicaoTaxaService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Remove (SoftDeletes) um item da composição, reduzindo o valor devido da taxa
 * — Etapa 4 de docs/migration/05-plano-composicao-taxas.md.
 *
 * Recusa remover item de taxa já quitada: reduzir o devido de uma competência
 * paga criaria um crédito silencioso. O caminho correto nesse caso é estornar
 * o pagamento primeiro.
 */
class RemoverItemTaxa
{
    public function __construct(private readonly ComposicaoTaxaService $composicaoService) {}

    public function executar(ItemTaxa $item, User $ator): void
    {
        if (! Gate::forUser($ator)->allows('delete', $item)) {
            throw new AuthorizationException('Sem permissão para alterar a composição da taxa.');
        }

        $taxa = $item->taxaCondominial;
        $pago = (string) ($taxa->pagamentoTaxas()->sum('valor_aplicado') ?: '0');

        if (bccomp($pago, '0', 2) > 0) {
            throw new DomainException(
                'A taxa '.$taxa->competencia_mes.'/'.$taxa->competencia_ano.' já tem pagamento aplicado. '
                .'Estorne o pagamento antes de reduzir a composição.'
            );
        }

        $this->composicaoService->removerItem($item);
    }
}
