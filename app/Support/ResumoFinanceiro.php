<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Pagamento;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Agregados financeiros do modelo novo (substitui ResumoFinanceiro no cutover).
 * Semântica preservada (EX-04): "mensalidades pagas contabilizadas por pago_em"
 * vira "aplicações em pagamento_taxa de taxas contabilizadas, pela data do
 * pagamento" (estornos entram negativos e se anulam); receitas e despesas
 * vêm de lancamentos_financeiros por natureza/data_lancamento.
 */
final class ResumoFinanceiro
{
    public static function saldoAte(string $data): float
    {
        return self::saldo('<=', $data);
    }

    public static function saldoAnterior(string $de): float
    {
        return self::saldo('<', $de);
    }

    /**
     * @return array{taxas: float, receitas: float, despesas: float}
     */
    public static function totaisEntre(string $de, string $ate): array
    {
        return [
            'taxas' => (float) self::aplicacoesContabilizadas()
                ->whereBetween('pagamentos_novo.data_pagamento', [$de, $ate])
                ->sum('pagamento_taxa.valor_aplicado'),
            'receitas' => (float) DB::table('lancamentos_financeiros')
                ->whereNull('deleted_at')->where('natureza', 'receita')
                ->whereBetween('data_lancamento', [$de, $ate])->sum('valor'),
            'despesas' => (float) DB::table('lancamentos_financeiros')
                ->whereNull('deleted_at')->where('natureza', 'despesa')
                ->whereBetween('data_lancamento', [$de, $ate])->sum('valor'),
        ];
    }

    /**
     * Expressão SQL portável para extrair o ano de uma coluna de data
     * (YEAR() no MySQL; strftime no sqlite dos testes).
     */
    public static function anoSql(string $coluna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', {$coluna}) AS INTEGER)"
            : "YEAR({$coluna})";
    }

    /**
     * Aplicações de pagamento em taxas CONTABILIZADAS (base dos agregados).
     * A tabela de pagamentos entra com o alias fixo `pagamentos_novo` (resolvido
     * pelo Model), então os consumidores sobrevivem ao rename da Fase 5.
     */
    public static function aplicacoesContabilizadas(): Builder
    {
        $tabelaPagamentos = (new Pagamento)->getTable();

        return DB::table('pagamento_taxa')
            ->join("{$tabelaPagamentos} as pagamentos_novo", 'pagamentos_novo.id', '=', 'pagamento_taxa.pagamento_id')
            ->join('taxas_condominiais', 'taxas_condominiais.id', '=', 'pagamento_taxa.taxa_condominial_id')
            ->whereNull('pagamentos_novo.deleted_at')
            ->whereNull('taxas_condominiais.deleted_at')
            ->where('taxas_condominiais.contabilizado', true);
    }

    private static function saldo(string $operador, string $data): float
    {
        $taxas = (float) self::aplicacoesContabilizadas()
            ->where('pagamentos_novo.data_pagamento', $operador, $data)
            ->sum('pagamento_taxa.valor_aplicado');

        $receitas = (float) DB::table('lancamentos_financeiros')
            ->whereNull('deleted_at')->where('natureza', 'receita')
            ->where('data_lancamento', $operador, $data)->sum('valor');

        $despesas = (float) DB::table('lancamentos_financeiros')
            ->whereNull('deleted_at')->where('natureza', 'despesa')
            ->where('data_lancamento', $operador, $data)->sum('valor');

        return $taxas + $receitas - $despesas;
    }
}
