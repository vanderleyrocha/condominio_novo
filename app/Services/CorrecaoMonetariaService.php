<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MetodoCorrecao;
use App\Models\Ipca;
use App\Support\ParametrosCondominio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Correção monetária pelo IPCA (BR-MIGRAR-008).
 *
 * Paridade com o legado (RN-21/RN-22): período com AMBAS as extremidades
 * inclusivas (mês do vencimento e mês da data-base) e, no método soma simples,
 * fórmula exata round(valor * (1 + soma_indices/100), 2).
 * O método composto é a alternativa configurável decidida em P7.
 */
class CorrecaoMonetariaService
{
    public function corrigirMensalidade(
        float $valorLiquido,
        CarbonInterface $vencimento,
        ?CarbonInterface $dataBase = null,
        ?MetodoCorrecao $metodo = null,
    ): float {
        $dataBase ??= now();
        $metodo ??= ParametrosCondominio::metodoCorrecao();

        // Guarda de não-correção: vencimento não anterior à data-base
        if ($vencimento->greaterThanOrEqualTo($dataBase)) {
            return $valorLiquido;
        }

        $indices = $this->indicesDoPeriodo($vencimento, $dataBase);

        return $this->aplicar($valorLiquido, $indices, $metodo);
    }

    /**
     * @return array{valor_original: float, ipca_acumulado: float, periodo: string, valor_corrigido: float, metodo: string, indices: Collection}
     */
    public function memoriaCalculo(
        float $valorLiquido,
        CarbonInterface $vencimento,
        ?CarbonInterface $dataBase = null,
        ?MetodoCorrecao $metodo = null,
    ): array {
        $dataBase ??= now();
        $metodo ??= ParametrosCondominio::metodoCorrecao();

        if ($vencimento->greaterThanOrEqualTo($dataBase)) {
            return [
                'valor_original' => $valorLiquido,
                'ipca_acumulado' => 0.0,
                'periodo' => $vencimento->format('m/Y').' a '.$dataBase->format('m/Y'),
                'valor_corrigido' => $valorLiquido,
                'metodo' => $metodo->value,
                'indices' => collect(),
            ];
        }

        $indices = $this->indicesDoPeriodo($vencimento, $dataBase);

        return [
            'valor_original' => $valorLiquido,
            'ipca_acumulado' => (float) $indices->sum('indice'),
            'periodo' => $vencimento->format('m/Y').' a '.$dataBase->format('m/Y'),
            'valor_corrigido' => $this->aplicar($valorLiquido, $indices, $metodo),
            'metodo' => $metodo->value,
            'indices' => $indices,
        ];
    }

    private function aplicar(float $valorLiquido, Collection $indices, MetodoCorrecao $metodo): float
    {
        if ($metodo === MetodoCorrecao::SomaSimples) {
            // Paridade de arredondamento com o legado: a soma dos índices é feita
            // pelo banco (aritmética DECIMAL exata via SUM), não em floats no PHP —
            // evita flips de centavo em fronteiras de round() (comparação golden 2026-07-16)
            $acumulado = (float) $indices->reduce(
                fn (string $carry, $i): string => bcadd($carry, (string) $i->indice, 4),
                '0',
            );

            return round($valorLiquido * (1 + $acumulado / 100), 2);
        }

        // Composta: aplica os índices mês a mês (P7); arredonda só no final
        $fator = 1.0;
        foreach ($indices as $indice) {
            $fator *= 1 + ((float) $indice->indice) / 100;
        }

        return round($valorLiquido * $fator, 2);
    }

    private function indicesDoPeriodo(CarbonInterface $vencimento, CarbonInterface $dataBase): Collection
    {
        // Consulta idêntica à do legado: extremidades inclusivas (RN-22)
        return Ipca::query()
            ->where(function ($q) use ($vencimento) {
                $q->where('ano', '>', $vencimento->year)
                    ->orWhere(function ($q) use ($vencimento) {
                        $q->where('ano', $vencimento->year)->where('mes', '>=', $vencimento->month);
                    });
            })
            ->where(function ($q) use ($dataBase) {
                $q->where('ano', '<', $dataBase->year)
                    ->orWhere(function ($q) use ($dataBase) {
                        $q->where('ano', $dataBase->year)->where('mes', '<=', $dataBase->month);
                    });
            })
            ->orderBy('ano')
            ->orderBy('mes')
            ->get(['ano', 'mes', 'indice']);
    }
}
