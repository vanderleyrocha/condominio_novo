<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Finalidade;
use App\Models\Pagamento;
use App\Models\TaxaCondominial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rateio do valor pago de uma taxa entre os itens que a compõem
 * (docs/migration/05-plano-composicao-taxas.md D-03) — **cascata por ordem**:
 * o total pago quita os itens na ordem `ordem` (taxa ordinária primeiro,
 * contribuições depois).
 *
 * Consequência a comunicar no relatório: numa taxa 100 + 50, um pagamento
 * parcial de 75,00 é rateado como 75 para o custeio e 0 para a pintura. A
 * arrecadação de uma campanha só é reconhecida depois de quitada a parcela
 * ordinária da competência. É intencional (protege o custeio).
 *
 * O rateio é DERIVADO: não existe tabela de alocação por item. A base é sempre
 * a soma de pagamento_taxa, então estornos (valor_aplicado negativo) se anulam
 * naturalmente antes da cascata.
 *
 * Toda aritmética em BCMath sobre strings decimais — nunca float.
 */
class RateioPorFinalidadeService
{
    private const ESCALA = 2;

    /**
     * Cálculo puro, sem tocar no banco — testável isoladamente.
     *
     * @param  string  $valorPagoTotal  soma de pagamento_taxa (pode ser <= 0)
     * @param  iterable<array-key, array{id: int, valor: string}>  $itens  já ordenados por `ordem`
     * @return array<int, string> [item_id => valor atribuído]
     */
    public function distribuir(string $valorPagoTotal, iterable $itens): array
    {
        // Soma negativa (estorno maior que o pago) não gera atribuição
        $saldo = bccomp($valorPagoTotal, '0', self::ESCALA) > 0
            ? $this->escalar($valorPagoTotal)
            : '0.00';

        $saida = [];

        foreach ($itens as $item) {
            $valorItem = bccomp($item['valor'], '0', self::ESCALA) > 0 ? $this->escalar($item['valor']) : '0.00';

            $aplicado = bccomp($saldo, $valorItem, self::ESCALA) >= 0 ? $valorItem : $saldo;

            $saida[$item['id']] = $aplicado;
            $saldo = bcsub($saldo, $aplicado, self::ESCALA);
        }

        return $saida;
    }

    /**
     * Excedente não atribuído a nenhum item (pago acima da soma dos itens —
     * acontece quando há acréscimo, que vive no nível da taxa, não do item).
     *
     * @param  iterable<array-key, array{id: int, valor: string}>  $itens
     */
    public function excedente(string $valorPagoTotal, iterable $itens): string
    {
        $atribuido = '0.00';

        foreach ($this->distribuir($valorPagoTotal, $itens) as $valor) {
            $atribuido = bcadd($atribuido, $valor, self::ESCALA);
        }

        $sobra = bcsub(
            bccomp($valorPagoTotal, '0', self::ESCALA) > 0 ? $this->escalar($valorPagoTotal) : '0.00',
            $atribuido,
            self::ESCALA
        );

        return bccomp($sobra, '0', self::ESCALA) > 0 ? $sobra : '0.00';
    }

    /**
     * Rateio dos pagamentos de uma taxa concreta.
     *
     * @return array<int, string> [item_id => valor atribuído]
     */
    public function distribuirTaxa(TaxaCondominial $taxa): array
    {
        $pago = (string) ($taxa->pagamentoTaxas()->sum('valor_aplicado') ?: '0');

        return $this->distribuir($pago, $this->itensParaRateio($taxa));
    }

    /**
     * Arrecadação ACUMULADA por finalidade, somando as duas fontes de receita:
     *  (a) rateio em cascata das aplicações em pagamento_taxa de taxas
     *      contabilizadas — a parcela de cada item efetivamente paga;
     *  (b) lançamentos financeiros de receita com a finalidade.
     *
     * Traz também o `gasto` — despesas lançadas contra a finalidade — e o
     * `saldo` (arrecadado − gasto), que é o que sobra do fundo. Sem o gasto o
     * fundo só cresceria: quando a pintura for de fato paga, o dinheiro
     * carimbado precisa sair do bolo vinculado.
     *
     * É deliberadamente acumulada (não por intervalo): a pergunta que o
     * relatório responde é "quanto já arrecadamos para X". `$ate` permite
     * congelar a leitura numa data.
     *
     * @return Collection<int|string, array{finalidade: ?Finalidade, cobrado: string, arrecadado_taxas: string, receitas: string, arrecadado: string, gasto: string, saldo: string, a_receber: string}>
     */
    public function arrecadadoPorFinalidade(?string $ate = null): Collection
    {
        $ate ??= '9999-12-31';

        $acumulado = [];

        $registrar = function (int|string $chave, string $campo, string $valor) use (&$acumulado): void {
            $acumulado[$chave] ??= [
                'cobrado' => '0.00', 'arrecadado_taxas' => '0.00', 'receitas' => '0.00', 'gasto' => '0.00',
            ];
            $acumulado[$chave][$campo] = bcadd($acumulado[$chave][$campo], $valor, self::ESCALA);
        };

        $pagoPorTaxa = $this->pagoPorTaxa($ate);

        TaxaCondominial::query()
            ->where('contabilizado', true)
            ->with('itens')
            ->chunkById(500, function (Collection $taxas) use (&$pagoPorTaxa, $registrar): void {
                foreach ($taxas as $taxa) {
                    $itens = $this->itensParaRateio($taxa);

                    if ($itens === []) {
                        continue;
                    }

                    $rateio = $this->distribuir($pagoPorTaxa[$taxa->id] ?? '0.00', $itens);

                    foreach ($taxa->itens as $item) {
                        $chave = $item->finalidade_id ?? 'sem_finalidade';
                        $registrar($chave, 'cobrado', (string) $item->valor);
                        $registrar($chave, 'arrecadado_taxas', $rateio[$item->id] ?? '0.00');
                    }
                }
            });

        DB::table('lancamentos_financeiros')
            ->whereNull('deleted_at')
            ->whereIn('natureza', ['receita', 'despesa'])
            ->where('data_lancamento', '<=', $ate)
            ->selectRaw('finalidade_id, natureza, SUM(valor) as total')
            ->groupBy('finalidade_id', 'natureza')
            ->get()
            ->each(fn (object $l) => $registrar(
                $l->finalidade_id ?? 'sem_finalidade',
                $l->natureza === 'despesa' ? 'gasto' : 'receitas',
                number_format((float) $l->total, 2, '.', '')
            ));

        $finalidades = Finalidade::query()->get()->keyBy('id');

        return collect($acumulado)
            ->map(function (array $linha, int|string $chave) use ($finalidades): array {
                $arrecadado = bcadd($linha['arrecadado_taxas'], $linha['receitas'], self::ESCALA);
                $aReceber = bcsub($linha['cobrado'], $linha['arrecadado_taxas'], self::ESCALA);

                return [
                    'finalidade' => $chave === 'sem_finalidade' ? null : $finalidades->get((int) $chave),
                    'cobrado' => $linha['cobrado'],
                    'arrecadado_taxas' => $linha['arrecadado_taxas'],
                    'receitas' => $linha['receitas'],
                    'arrecadado' => $arrecadado,
                    'gasto' => $linha['gasto'],
                    'saldo' => bcsub($arrecadado, $linha['gasto'], self::ESCALA),
                    'a_receber' => bccomp($aReceber, '0', self::ESCALA) > 0 ? $aReceber : '0.00',
                ];
            })
            ->sortByDesc('arrecadado');
    }

    /**
     * Linhas das finalidades RESTRITAS — recursos carimbados, indisponíveis
     * para o custeio ordinário. Ordenadas por nome (é uma lista de leitura, não
     * um ranking).
     *
     * @return Collection<int|string, array{finalidade: ?Finalidade, cobrado: string, arrecadado_taxas: string, receitas: string, arrecadado: string, gasto: string, saldo: string, a_receber: string}>
     */
    public function vinculadoPorFinalidade(?string $ate = null): Collection
    {
        return $this->arrecadadoPorFinalidade($ate)
            ->filter(fn (array $l): bool => (bool) $l['finalidade']?->restrita)
            ->sortBy(fn (array $l): string => (string) $l['finalidade']->nome)
            ->values();
    }

    /**
     * Total carimbado hoje = soma dos saldos das finalidades restritas.
     *
     * Saldo negativo (gastou-se mais do que se arrecadou para a finalidade)
     * entra como zero: aquele fundo não tem nada guardado, e deixá-lo negativo
     * liberaria caixa de outras finalidades para o custeio. O buraco aparece
     * na linha da finalidade, no relatório — não no total.
     *
     * @param  Collection<int|string, array{saldo: string}>  $linhas  saída de vinculadoPorFinalidade()
     */
    public function somarSaldoVinculado(Collection $linhas): string
    {
        return $linhas->reduce(
            fn (string $carry, array $l): string => bccomp($l['saldo'], '0', self::ESCALA) > 0
                ? bcadd($carry, $l['saldo'], self::ESCALA)
                : $carry,
            '0.00'
        );
    }

    /**
     * Soma de pagamento_taxa por taxa até a data (pagamentos não excluídos).
     *
     * @return array<int, string>
     */
    private function pagoPorTaxa(string $ate): array
    {
        $tabelaPagamentos = (new Pagamento)->getTable();

        return DB::table('pagamento_taxa')
            ->join("{$tabelaPagamentos} as p", 'p.id', '=', 'pagamento_taxa.pagamento_id')
            ->whereNull('p.deleted_at')
            ->where('p.data_pagamento', '<=', $ate)
            ->selectRaw('pagamento_taxa.taxa_condominial_id as taxa_id, SUM(pagamento_taxa.valor_aplicado) as total')
            ->groupBy('pagamento_taxa.taxa_condominial_id')
            ->pluck('total', 'taxa_id')
            ->map(fn ($v): string => number_format((float) $v, 2, '.', ''))
            ->all();
    }

    /**
     * Normaliza para 2 casas decimais. Necessário porque SUM() do sqlite (dos
     * testes) devolve `75` onde o MySQL devolve `75.00`, e bcsub propagaria a
     * escala de entrada para o resultado.
     */
    private function escalar(string $valor): string
    {
        return number_format((float) $valor, self::ESCALA, '.', '');
    }

    /**
     * @return list<array{id: int, valor: string}>
     */
    private function itensParaRateio(TaxaCondominial $taxa): array
    {
        return $taxa->itens
            ->sortBy([['ordem', 'asc'], ['id', 'asc']])
            ->map(fn ($item): array => ['id' => (int) $item->id, 'valor' => (string) $item->valor])
            ->values()
            ->all();
    }
}
