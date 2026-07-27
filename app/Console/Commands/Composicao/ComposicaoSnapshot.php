<?php

declare(strict_types=1);

namespace App\Console\Commands\Composicao;

use App\Models\TaxaCondominial;
use App\Support\ResumoFinanceiro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Etapa 0 do plano de composição de taxas (docs/migration/05-plano-composicao-taxas.md):
 * congela os agregados financeiros que a evolução NÃO pode alterar.
 *
 *   php artisan composicao:snapshot            → grava o snapshot
 *   php artisan composicao:snapshot --comparar  → compara o estado atual com o
 *                                                 snapshot e falha (exit 1) se divergir
 *
 * Critério de aceite de todas as etapas: diferença ZERO. Toda comparação é feita
 * sobre strings decimais com 2 casas — nunca float — para não introduzir ruído
 * de ponto flutuante no próprio gate de paridade.
 */
class ComposicaoSnapshot extends Command
{
    protected $signature = 'composicao:snapshot
        {--arquivo= : Caminho do snapshot (default: storage/app/golden/composicao/snapshot.json)}
        {--comparar : Compara o estado atual com o snapshot em vez de gravar}
        {--limite=25 : Máximo de divergências listadas na comparação}';

    protected $description = 'Congela (ou confere) os agregados financeiros intocáveis da composição de taxas';

    public function handle(): int
    {
        $arquivo = $this->option('arquivo') ?: storage_path('app/golden/composicao/snapshot.json');

        return $this->option('comparar')
            ? $this->comparar($arquivo)
            : $this->gravar($arquivo);
    }

    /**
     * @return array<string, mixed>
     */
    public function coletar(): array
    {
        $agora = now();

        return [
            'saldo_total' => $this->dec(ResumoFinanceiro::saldoAte('9999-12-31')),
            'soma_pagamento_taxa' => $this->somaPagamentoTaxa(),
            'lancamentos' => $this->lancamentos(),
            'totais_por_ano' => $this->totaisPorAno(),
            'taxas' => $this->taxas(),
            'inadimplencia_por_unidade' => $this->inadimplenciaPorUnidade(),
            'gerado_em' => $agora->toDateTimeString(),
        ];
    }

    private function gravar(string $arquivo): int
    {
        $dados = $this->coletar();

        $dir = dirname($arquivo);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $arquivo,
            json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info('Snapshot gravado em '.$arquivo);
        $this->resumir($dados);

        return self::SUCCESS;
    }

    private function comparar(string $arquivo): int
    {
        if (! is_file($arquivo)) {
            $this->error("Snapshot não encontrado em {$arquivo} — rode `composicao:snapshot` primeiro.");

            return self::FAILURE;
        }

        /** @var array<string, mixed> $antes */
        $antes = json_decode((string) file_get_contents($arquivo), true, 512, JSON_THROW_ON_ERROR);
        $depois = $this->coletar();

        // gerado_em é metadado, não agregado
        unset($antes['gerado_em'], $depois['gerado_em']);

        $divergencias = $this->diferencas($antes, $depois);

        if ($divergencias === []) {
            $this->info('Paridade OK — diferença zero em todos os agregados.');
            $this->resumir($depois);

            return self::SUCCESS;
        }

        $limite = (int) $this->option('limite');

        $this->error(count($divergencias).' divergência(s) em relação ao snapshot:');

        foreach (array_slice($divergencias, 0, $limite) as $par) {
            $this->line("  {$par['caminho']}: antes={$par['antes']} depois={$par['depois']}");
        }

        if (count($divergencias) > $limite) {
            $this->line('  ... ('.(count($divergencias) - $limite).' outras omitidas)');
        }

        return self::FAILURE;
    }

    /**
     * Diff plano e recursivo (só folhas), preservando o caminho da chave.
     *
     * @param  array<mixed>  $antes
     * @param  array<mixed>  $depois
     * @return list<array{caminho: string, antes: string, depois: string}>
     */
    private function diferencas(array $antes, array $depois, string $prefixo = ''): array
    {
        $saida = [];

        foreach (array_keys($antes + $depois) as $chave) {
            $caminho = $prefixo === '' ? (string) $chave : "{$prefixo}.{$chave}";
            $a = $antes[$chave] ?? null;
            $d = $depois[$chave] ?? null;

            if (is_array($a) && is_array($d)) {
                $saida = [...$saida, ...$this->diferencas($a, $d, $caminho)];

                continue;
            }

            if ((string) $a !== (string) $d) {
                $saida[] = [
                    'caminho' => $caminho,
                    'antes' => $a === null ? '(ausente)' : (string) $a,
                    'depois' => $d === null ? '(ausente)' : (string) $d,
                ];
            }
        }

        return $saida;
    }

    private function somaPagamentoTaxa(): string
    {
        return $this->dec(
            DB::table('pagamento_taxa')->sum('valor_aplicado')
        );
    }

    /**
     * @return array<string, string>
     */
    private function lancamentos(): array
    {
        $base = fn (string $natureza) => DB::table('lancamentos_financeiros')
            ->whereNull('deleted_at')->where('natureza', $natureza);

        return [
            'receita_total' => $this->dec($base('receita')->sum('valor')),
            'receita_quantidade' => (string) $base('receita')->count(),
            'despesa_total' => $this->dec($base('despesa')->sum('valor')),
            'despesa_quantidade' => (string) $base('despesa')->count(),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function totaisPorAno(): array
    {
        $anos = TaxaCondominial::query()
            ->distinct()
            ->orderBy('competencia_ano')
            ->pluck('competencia_ano')
            ->all();

        $saida = [];

        foreach ($anos as $ano) {
            $totais = ResumoFinanceiro::totaisEntre("{$ano}-01-01", "{$ano}-12-31");

            $saida[(string) $ano] = [
                'taxas' => $this->dec($totais['taxas']),
                'receitas' => $this->dec($totais['receitas']),
                'despesas' => $this->dec($totais['despesas']),
            ];
        }

        return $saida;
    }

    /**
     * Estado devido/derivado de cada taxa. É a garantia central: a decomposição
     * em itens não pode mexer em valor, desconto, acréscimo nem status.
     *
     * @return array<string, string>
     */
    private function taxas(): array
    {
        return DB::table('taxas_condominiais')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'valor_original', 'valor_desconto', 'valor_acrescimo', 'status'])
            ->mapWithKeys(fn (object $t): array => [
                (string) $t->id => implode('|', [
                    $this->dec($t->valor_original),
                    $this->dec($t->valor_desconto),
                    $this->dec($t->valor_acrescimo),
                    $t->status,
                ]),
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function inadimplenciaPorUnidade(): array
    {
        return TaxaCondominial::query()
            ->emAberto()
            ->selectRaw('unidade_id, COUNT(*) as quantidade, SUM(valor_original - valor_desconto) as total')
            ->groupBy('unidade_id')
            ->orderBy('unidade_id')
            ->get()
            ->mapWithKeys(fn (object $l): array => [
                (string) $l->unidade_id => [
                    'quantidade' => (string) $l->quantidade,
                    'total' => $this->dec($l->total),
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function resumir(array $dados): void
    {
        $this->table(['Agregado', 'Valor'], [
            ['saldo_total', $dados['saldo_total']],
            ['soma_pagamento_taxa', $dados['soma_pagamento_taxa']],
            ['receitas', $dados['lancamentos']['receita_total'].' ('.$dados['lancamentos']['receita_quantidade'].')'],
            ['despesas', $dados['lancamentos']['despesa_total'].' ('.$dados['lancamentos']['despesa_quantidade'].')'],
            ['taxas conferidas', (string) count($dados['taxas'])],
            ['unidades inadimplentes', (string) count($dados['inadimplencia_por_unidade'])],
        ]);
    }

    private function dec(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
