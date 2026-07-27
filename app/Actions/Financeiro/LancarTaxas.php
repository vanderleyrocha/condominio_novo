<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Enums\StatusTaxa;
use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use App\Models\Finalidade;
use App\Models\PlanoConta;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Support\ConfiguracoesCondominio;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lançamento em lote no modelo novo (substitui LancarMensalidades no cutover):
 * 12 taxas × todas as unidades do ano, vencimento no último dia de cada mês,
 * contabilizado = 1, status inicial 'aberto'. Relançar o mesmo ano é bloqueado
 * (EX-01) e a unique (unidade, ano, mes) garante no banco.
 *
 * ETAPA 4 de docs/migration/05-plano-composicao-taxas.md: a taxa deixa de ser
 * um valor solto e nasce COMPOSTA —
 *   · item ordem 0: taxa condominial, no valor ordinário;
 *   · item ordem 1..n: uma linha por cobrança extraordinária ativa cuja
 *     vigência cubra a competência, no seu valor_por_unidade.
 * `valor_original` é sempre a soma dos itens (invariante §3.4).
 *
 * ATENÇÃO ao parâmetro $valor: ele é o valor da taxa ORDINÁRIA, não o total da
 * mensalidade. As contribuições recorrentes são somadas por cima.
 */
class LancarTaxas
{
    private const DESC_ORDINARIA = 'Taxa condominial';

    private const FINALIDADE_CUSTEIO = 'Custeio ordinário';

    /**
     * @return int quantidade de taxas lançadas
     */
    public function executar(int $ano, ?string $valor = null): int
    {
        $valor ??= ConfiguracoesCondominio::taxaMensalidadePadrao();

        return DB::transaction(function () use ($ano, $valor): int {
            if (TaxaCondominial::query()->where('competencia_ano', $ano)->exists()) {
                throw new DomainException("As taxas do ano {$ano} já foram lançadas.");
            }

            $unidades = Unidade::query()->orderBy('id')->get();

            if ($unidades->isEmpty()) {
                throw new DomainException('Não há unidades cadastradas para lançar taxas.');
            }

            $condominioId = (int) (Condominio::query()->value('id')
                ?? throw new RuntimeException('Nenhum condomínio cadastrado.'));

            $planoOrdinaria = $this->planoOrdinaria($condominioId);
            $planoExtraordinaria = $this->planoExtraordinaria($condominioId);
            $finalidadeCusteio = $this->finalidadeCusteio($condominioId);
            $campanhas = $this->campanhasRecorrentes();

            $agora = now();
            $taxas = [];

            foreach ($unidades as $unidade) {
                for ($mes = 1; $mes <= 12; $mes++) {
                    // Vencimento no último dia do mês — paridade com o legado (RN-03)
                    $vencimento = sprintf('%d-%02d-%02d', $ano, $mes, cal_days_in_month(CAL_GREGORIAN, $mes, $ano));

                    $taxas[] = [
                        'unidade_id' => $unidade->id,
                        'competencia_mes' => $mes,
                        'competencia_ano' => $ano,
                        'vencimento' => $vencimento,
                        'valor_original' => $this->totalDaCompetencia($valor, $campanhas, $ano, $mes),
                        'valor_desconto' => '0.00',
                        'valor_acrescimo' => '0.00',
                        'status' => StatusTaxa::Aberto->value,
                        'contabilizado' => true,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                }
            }

            TaxaCondominial::query()->insert($taxas);

            $this->criarItens(
                $ano, $valor, $campanhas, $planoOrdinaria, $planoExtraordinaria, $finalidadeCusteio, $agora,
            );

            return count($taxas);
        });
    }

    /**
     * Prévia da composição de uma competência — usada pela tela de lançamento
     * para o usuário ver o total antes de confirmar.
     *
     * @return list<array{descricao: string, valor: string}>
     */
    public function previaComposicao(int $ano, int $mes, ?string $valor = null): array
    {
        $valor ??= ConfiguracoesCondominio::taxaMensalidadePadrao();

        $linhas = [['descricao' => self::DESC_ORDINARIA, 'valor' => bcadd($valor, '0', 2)]];

        foreach ($this->campanhasRecorrentes() as $campanha) {
            if ($campanha->cobreCompetencia($ano, $mes)) {
                $linhas[] = ['descricao' => $campanha->nome, 'valor' => (string) $campanha->valor_por_unidade];
            }
        }

        return $linhas;
    }

    /**
     * @param  Collection<int, CobrancaExtraordinaria>  $campanhas
     */
    private function totalDaCompetencia(string $valorOrdinario, Collection $campanhas, int $ano, int $mes): string
    {
        $total = bcadd($valorOrdinario, '0', 2);

        foreach ($campanhas as $campanha) {
            if ($campanha->cobreCompetencia($ano, $mes)) {
                $total = bcadd($total, (string) $campanha->valor_por_unidade, 2);
            }
        }

        return $total;
    }

    /**
     * Itens em bulk: as taxas já foram inseridas, então basta lê-las de volta
     * e criar as linhas correspondentes.
     *
     * @param  Collection<int, CobrancaExtraordinaria>  $campanhas
     */
    private function criarItens(
        int $ano,
        string $valorOrdinario,
        Collection $campanhas,
        PlanoConta $planoOrdinaria,
        ?PlanoConta $planoExtraordinaria,
        Finalidade $finalidadeCusteio,
        CarbonInterface $agora,
    ): void {
        $linhas = [];

        foreach (TaxaCondominial::query()->where('competencia_ano', $ano)->orderBy('id')->get(['id', 'competencia_mes']) as $taxa) {
            $linhas[] = [
                'taxa_condominial_id' => $taxa->id,
                'plano_conta_id' => $planoOrdinaria->id,
                'finalidade_id' => $finalidadeCusteio->id,
                'descricao' => self::DESC_ORDINARIA,
                'valor' => bcadd($valorOrdinario, '0', 2),
                'ordem' => 0,
                'origem_type' => null,
                'origem_id' => null,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];

            $ordem = 1;

            foreach ($campanhas as $campanha) {
                if (! $campanha->cobreCompetencia($ano, (int) $taxa->competencia_mes)) {
                    continue;
                }

                $linhas[] = [
                    'taxa_condominial_id' => $taxa->id,
                    'plano_conta_id' => $planoExtraordinaria?->id ?? $planoOrdinaria->id,
                    'finalidade_id' => $campanha->finalidade_id,
                    'descricao' => $campanha->nome,
                    'valor' => (string) $campanha->valor_por_unidade,
                    'ordem' => $ordem++,
                    'origem_type' => CobrancaExtraordinaria::class,
                    'origem_id' => $campanha->id,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }
        }

        foreach (array_chunk($linhas, 500) as $lote) {
            DB::table('itens_taxa_condominial')->insert($lote);
        }
    }

    /**
     * Campanhas que geram item mensal: ativas e com valor por unidade definido.
     * Sem `valor_por_unidade` a campanha é apenas um alvo de arrecadação com
     * rateio manual, não uma cobrança recorrente.
     *
     * @return Collection<int, CobrancaExtraordinaria>
     */
    private function campanhasRecorrentes(): Collection
    {
        return CobrancaExtraordinaria::query()
            ->where('ativa', true)
            ->whereNotNull('valor_por_unidade')
            ->where('valor_por_unidade', '>', 0)
            ->orderBy('id')
            ->get();
    }

    private function planoOrdinaria(int $condominioId): PlanoConta
    {
        return PlanoConta::query()
            ->where('condominio_id', $condominioId)
            ->where('codigo', 'R-001')
            ->first() ?? throw new DomainException(
                'Plano de contas R-001 (Receita de Taxa Condominial) não cadastrado.'
            );
    }

    private function planoExtraordinaria(int $condominioId): ?PlanoConta
    {
        return PlanoConta::query()
            ->where('condominio_id', $condominioId)
            ->where('codigo', 'R-002')
            ->first();
    }

    private function finalidadeCusteio(int $condominioId): Finalidade
    {
        return Finalidade::query()->firstOrCreate(
            ['condominio_id' => $condominioId, 'nome' => self::FINALIDADE_CUSTEIO],
            [
                'descricao' => 'Despesas correntes de manutenção e administração do condomínio.',
                'ativa' => true,
            ],
        );
    }
}
