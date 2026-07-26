<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MetodoCorrecao;
use App\Enums\TipoIndiceEconomico;
use App\Models\IndiceEconomico;
use App\Support\ConfiguracoesCondominio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Correção monetária no modelo novo (substitui CorrecaoMonetariaService no
 * cutover): lê indices_economicos (série configurável, IPCA por padrão) e
 * configuracoes. A MATEMÁTICA é idêntica ao serviço do legado — extremidades
 * inclusivas (RN-21/RN-22), soma dos índices via BCMath e
 * round(valor * (1 + soma/100), 2) — paridade golden files.
 */
class CorrecaoMonetariaService
{
    public function corrigirTaxa(
        float $valorLiquido,
        CarbonInterface $vencimento,
        ?CarbonInterface $dataBase = null,
        ?MetodoCorrecao $metodo = null,
        TipoIndiceEconomico $tipoIndice = TipoIndiceEconomico::Ipca,
    ): float {
        $dataBase ??= now();
        $metodo ??= ConfiguracoesCondominio::metodoCorrecao();

        // Guarda de não-correção: vencimento não anterior à data-base
        if ($vencimento->greaterThanOrEqualTo($dataBase)) {
            return $valorLiquido;
        }

        $indices = $this->indicesDoPeriodo($tipoIndice, $vencimento, $dataBase);

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
        TipoIndiceEconomico $tipoIndice = TipoIndiceEconomico::Ipca,
    ): array {
        $dataBase ??= now();
        $metodo ??= ConfiguracoesCondominio::metodoCorrecao();

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

        $indices = $this->indicesDoPeriodo($tipoIndice, $vencimento, $dataBase);

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
            // Paridade de arredondamento: soma via BCMath, nunca floats parciais
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

    private function indicesDoPeriodo(
        TipoIndiceEconomico $tipoIndice,
        CarbonInterface $vencimento,
        CarbonInterface $dataBase,
    ): Collection {
        // Consulta idêntica à do legado: extremidades inclusivas (RN-22)
        return IndiceEconomico::query()
            ->where('tipo', $tipoIndice->value)
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
