<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ItemTaxa;
use App\Models\TaxaCondominial;
use Illuminate\Support\Facades\DB;

/**
 * Serviço ÚNICO de escrita de `taxas_condominiais.valor_original`
 * (docs/migration/05-plano-composicao-taxas.md D-02): a coluna é cache de
 * leitura da soma dos itens, exatamente como `status` é cache da soma de
 * pagamento_taxa. Mesmo padrão de StatusTaxaService.
 *
 * Invariante mantida (§3.4):
 *   valor_original = SUM(itens_taxa_condominial.valor) dos itens não excluídos
 *
 * Toda aritmética em BCMath sobre strings decimais — nunca float
 * (convenção obrigatória de 03-modelo-dados.md).
 */
class ComposicaoTaxaService
{
    private const ESCALA = 2;

    public function __construct(private readonly StatusTaxaService $statusService) {}

    /**
     * Cálculo puro, sem tocar no banco — testável isoladamente.
     *
     * @param  iterable<array-key, string>  $valores
     */
    public function somar(iterable $valores): string
    {
        $total = '0.00';

        foreach ($valores as $valor) {
            $total = bcadd($total, $valor, self::ESCALA);
        }

        return $total;
    }

    /**
     * Recalcula e persiste o valor_original a partir dos itens vigentes.
     * Taxa sem itens é deixada intacta: significa que ela ainda não foi
     * decomposta (o backfill da Etapa 3 é quem cria o item inicial), e zerar
     * o valor aqui destruiria o valor devido de uma taxa legítima.
     */
    public function recalcular(TaxaCondominial $taxa): string
    {
        $itens = $taxa->itens()->pluck('valor');

        if ($itens->isEmpty()) {
            return (string) $taxa->valor_original;
        }

        $total = $this->somar($itens->map(fn ($v): string => (string) $v));

        if (bccomp((string) $taxa->valor_original, $total, self::ESCALA) !== 0) {
            $taxa->forceFill(['valor_original' => $total])->save();
        }

        // O valor devido pode ter mudado — o status derivado acompanha
        $this->statusService->recalcular($taxa->refresh());

        return $total;
    }

    /**
     * @param  array{plano_conta_id: int, descricao: string, valor: string, finalidade_id?: ?int, ordem?: int, origem_type?: ?string, origem_id?: ?int}  $dados
     */
    public function adicionarItem(TaxaCondominial $taxa, array $dados): ItemTaxa
    {
        return DB::transaction(function () use ($taxa, $dados): ItemTaxa {
            $item = $taxa->itens()->create($dados + ['ordem' => $dados['ordem'] ?? $this->proximaOrdem($taxa)]);

            $this->recalcular($taxa);

            return $item;
        });
    }

    /**
     * @param  array{plano_conta_id?: int, descricao?: string, valor?: string, finalidade_id?: ?int, ordem?: int}  $dados
     */
    public function atualizarItem(ItemTaxa $item, array $dados): ItemTaxa
    {
        return DB::transaction(function () use ($item, $dados): ItemTaxa {
            $item->update($dados);

            $this->recalcular($item->taxaCondominial);

            return $item;
        });
    }

    /**
     * Remoção lógica (SoftDeletes) — o histórico do item permanece auditável.
     * A última linha não pode sair: uma taxa sem itens perde a invariante e
     * volta a ser um valor solto. Para "zerar" a competência, ajuste o valor.
     */
    public function removerItem(ItemTaxa $item): void
    {
        DB::transaction(function () use ($item): void {
            $taxa = $item->taxaCondominial;

            if ($taxa->itens()->count() <= 1) {
                throw new \DomainException(
                    'A taxa precisa ter ao menos um item. Para isentar a competência, ajuste o valor para 0,00.'
                );
            }

            $item->delete();

            $this->recalcular($taxa);
        });
    }

    /**
     * Primeiro item da taxa é a ordinária (ordem 0); os seguintes vêm depois
     * dela na cascata de quitação.
     */
    private function proximaOrdem(TaxaCondominial $taxa): int
    {
        $maiorOrdem = $taxa->itens()->max('ordem');

        return $maiorOrdem === null ? 0 : (int) $maiorOrdem + 1;
    }
}
