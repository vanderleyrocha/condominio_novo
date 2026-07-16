<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Despesa;
use App\Models\Mensalidade;
use App\Models\Receita;

/**
 * Query object dos agregados financeiros compartilhados entre o Resumo e o Painel.
 *
 * Aplica a correção EX-04: o saldo considera mensalidades contabilizadas com
 * ano de referência = pago_em (SEM orWhereNull), somadas às receitas e
 * subtraídas as despesas.
 */
final class ResumoFinanceiro
{
    /**
     * Saldo acumulado até a data (inclusive).
     */
    public static function saldoAte(string $data): float
    {
        return self::saldo('<=', $data);
    }

    /**
     * Saldo acumulado antes da data (exclusive).
     */
    public static function saldoAnterior(string $de): float
    {
        return self::saldo('<', $de);
    }

    /**
     * Totais do período (mensalidades pagas contabilizadas, receitas e despesas).
     *
     * @return array{mensalidades: float, receitas: float, despesas: float}
     */
    public static function totaisEntre(string $de, string $ate): array
    {
        return [
            'mensalidades' => (float) Mensalidade::query()
                ->where('contabilizado', true)
                ->whereNotNull('pago_em')
                ->whereBetween('pago_em', [$de, $ate])
                ->sum('valor_pago'),
            'receitas' => (float) Receita::query()->whereBetween('data', [$de, $ate])->sum('valor'),
            'despesas' => (float) Despesa::query()->whereBetween('data', [$de, $ate])->sum('valor'),
        ];
    }

    private static function saldo(string $operador, string $data): float
    {
        $mensalidades = (float) Mensalidade::query()
            ->where('contabilizado', true)
            ->whereNotNull('pago_em')
            ->where('pago_em', $operador, $data)
            ->sum('valor_pago');

        $receitas = (float) Receita::query()->where('data', $operador, $data)->sum('valor');
        $despesas = (float) Despesa::query()->where('data', $operador, $data)->sum('valor');

        return $mensalidades + $receitas - $despesas;
    }
}
