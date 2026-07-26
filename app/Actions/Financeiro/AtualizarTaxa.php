<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\TaxaCondominial;
use App\Models\User;
use App\Services\StatusTaxaService;
use Illuminate\Support\Facades\Gate;

/**
 * Edição individual de taxa no modelo novo (substitui AtualizarMensalidade).
 * Diferença estrutural: o valor pago NÃO é editável aqui — pagamentos entram
 * por pagamento_taxa (módulo de pagamentos ou grade anual) e o status é
 * recalculado pelo serviço único após qualquer mudança no valor devido.
 * `contabilizado` só muda para quem pode gerenciá-lo (RN-08).
 */
class AtualizarTaxa
{
    public function __construct(private readonly StatusTaxaService $statusService) {}

    /**
     * @param  array{valor_original: string, valor_desconto: string, valor_acrescimo: string, vencimento: string, contabilizado?: bool}  $dados
     */
    public function executar(TaxaCondominial $taxa, array $dados, User $ator): TaxaCondominial
    {
        if (! Gate::forUser($ator)->allows('gerenciarContabilizado', TaxaCondominial::class)) {
            unset($dados['contabilizado']);
        }

        $taxa->update($dados);

        // O valor devido pode ter mudado — o status derivado acompanha
        $this->statusService->recalcular($taxa);

        return $taxa;
    }
}
