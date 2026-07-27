<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\CobrancaExtraordinaria;
use App\Models\ItemTaxa;
use App\Models\PlanoConta;
use App\Models\TaxaCondominial;
use App\Services\ComposicaoTaxaService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aplica (ou retira) uma cobrança extraordinária como item das taxas de um
 * intervalo de competências — Etapa 4 de docs/migration/05-plano-composicao-taxas.md.
 * Substitui a manipulação do pivô `cobranca_extraordinaria_taxa`, descontinuado
 * na Etapa 6.
 *
 * Regra de segurança (a mesma de RemoverItemTaxa): competência com pagamento
 * aplicado não é alterada, para não mexer no valor devido de algo já pago.
 * As taxas ignoradas são devolvidas para o chamador exibir.
 */
class AplicarCobrancaEmTaxas
{
    public function __construct(private readonly ComposicaoTaxaService $composicaoService) {}

    /**
     * @return array{aplicadas: int, ignoradas: list<string>}
     */
    public function aplicar(
        CobrancaExtraordinaria $cobranca,
        int $anoInicio,
        int $mesInicio,
        int $anoFim,
        int $mesFim,
    ): array {
        if ($cobranca->valor_por_unidade === null || bccomp((string) $cobranca->valor_por_unidade, '0', 2) <= 0) {
            throw new DomainException(
                'Defina o valor por unidade da cobrança antes de aplicá-la nas taxas.'
            );
        }

        $plano = $this->plano($cobranca);
        $aplicadas = 0;
        $ignoradas = [];

        DB::transaction(function () use (
            $cobranca, $anoInicio, $mesInicio, $anoFim, $mesFim, $plano, &$aplicadas, &$ignoradas
        ): void {
            foreach ($this->taxasNoIntervalo($anoInicio, $mesInicio, $anoFim, $mesFim) as $taxa) {
                $rotulo = "{$taxa->competencia_mes}/{$taxa->competencia_ano} · unidade {$taxa->unidade_id}";

                if ($taxa->itens()->where('descricao', $cobranca->nome)->exists()) {
                    continue; // já aplicada — idempotente
                }

                if (bccomp((string) ($taxa->pagamentoTaxas()->sum('valor_aplicado') ?: '0'), '0', 2) > 0) {
                    $ignoradas[] = $rotulo.' (já tem pagamento aplicado)';

                    continue;
                }

                $this->composicaoService->adicionarItem($taxa, [
                    'plano_conta_id' => $plano->id,
                    'finalidade_id' => $cobranca->finalidade_id,
                    'descricao' => $cobranca->nome,
                    'valor' => (string) $cobranca->valor_por_unidade,
                    'origem_type' => CobrancaExtraordinaria::class,
                    'origem_id' => $cobranca->id,
                ]);

                $aplicadas++;
            }
        });

        return ['aplicadas' => $aplicadas, 'ignoradas' => $ignoradas];
    }

    /**
     * @return array{retiradas: int, ignoradas: list<string>}
     */
    public function retirar(
        CobrancaExtraordinaria $cobranca,
        int $anoInicio,
        int $mesInicio,
        int $anoFim,
        int $mesFim,
    ): array {
        $retiradas = 0;
        $ignoradas = [];

        DB::transaction(function () use (
            $cobranca, $anoInicio, $mesInicio, $anoFim, $mesFim, &$retiradas, &$ignoradas
        ): void {
            foreach ($this->taxasNoIntervalo($anoInicio, $mesInicio, $anoFim, $mesFim) as $taxa) {
                $item = $taxa->itens()
                    ->where('origem_type', CobrancaExtraordinaria::class)
                    ->where('origem_id', $cobranca->id)
                    ->first();

                if (! $item instanceof ItemTaxa) {
                    continue;
                }

                $rotulo = "{$taxa->competencia_mes}/{$taxa->competencia_ano} · unidade {$taxa->unidade_id}";

                if (bccomp((string) ($taxa->pagamentoTaxas()->sum('valor_aplicado') ?: '0'), '0', 2) > 0) {
                    $ignoradas[] = $rotulo.' (já tem pagamento aplicado)';

                    continue;
                }

                $this->composicaoService->removerItem($item);
                $retiradas++;
            }
        });

        return ['retiradas' => $retiradas, 'ignoradas' => $ignoradas];
    }

    /**
     * @return Collection<int, TaxaCondominial>
     */
    private function taxasNoIntervalo(int $anoInicio, int $mesInicio, int $anoFim, int $mesFim)
    {
        // Competência como número comparável (ano*100 + mês) — evita OR aninhado
        $de = $anoInicio * 100 + $mesInicio;
        $ate = $anoFim * 100 + $mesFim;

        if ($de > $ate) {
            throw new DomainException('A competência inicial é posterior à final.');
        }

        return TaxaCondominial::query()
            ->whereRaw('(competencia_ano * 100 + competencia_mes) BETWEEN ? AND ?', [$de, $ate])
            ->orderBy('unidade_id')
            ->orderBy('competencia_ano')
            ->orderBy('competencia_mes')
            ->get();
    }

    private function plano(CobrancaExtraordinaria $cobranca): PlanoConta
    {
        return PlanoConta::query()
            ->where('condominio_id', $cobranca->condominio_id)
            ->whereIn('codigo', ['R-002', 'R-001'])
            ->orderBy('codigo', 'desc')
            ->first() ?? throw new DomainException(
                'Nenhum plano de contas de receita cadastrado para classificar a cobrança.'
            );
    }
}
