<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\TaxaCondominial;
use App\Models\User;
use App\Services\ComposicaoTaxaService;
use App\Services\StatusTaxaService;
use Illuminate\Support\Facades\Gate;

/**
 * Edição individual de taxa no modelo novo (substitui AtualizarMensalidade).
 *
 * Diferenças estruturais:
 *  - o valor pago NÃO é editável aqui — pagamentos entram por pagamento_taxa
 *    (módulo de pagamentos ou grade anual);
 *  - `valor_original` também NÃO é editável (Etapa 4 de
 *    05-plano-composicao-taxas.md): é a soma dos itens da composição, então o
 *    valor muda editando os itens (SalvarItemTaxa/RemoverItemTaxa). Se vier no
 *    array é ignorado, e o valor é reafirmado a partir dos itens.
 *  - `contabilizado` só muda para quem pode gerenciá-lo (RN-08).
 *
 * O status derivado é recalculado após qualquer mudança no valor devido.
 */
class AtualizarTaxa
{
    public function __construct(
        private readonly StatusTaxaService $statusService,
        private readonly ComposicaoTaxaService $composicaoService,
    ) {}

    /**
     * @param  array{valor_desconto: string, valor_acrescimo: string, vencimento: string, contabilizado?: bool}  $dados
     */
    public function executar(TaxaCondominial $taxa, array $dados, User $ator): TaxaCondominial
    {
        if (! Gate::forUser($ator)->allows('gerenciarContabilizado', TaxaCondominial::class)) {
            unset($dados['contabilizado']);
        }

        // O valor devido vem da composição, não do formulário
        unset($dados['valor_original']);

        $taxa->update($dados);

        // recalcular() reafirma valor_original = SUM(itens) e já recalcula o
        // status; a chamada explícita cobre a taxa ainda sem itens (legado)
        $this->composicaoService->recalcular($taxa);
        $this->statusService->recalcular($taxa);

        return $taxa;
    }
}
