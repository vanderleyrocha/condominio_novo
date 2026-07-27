<?php

declare(strict_types=1);

namespace App\Console\Commands\Composicao;

use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use App\Models\Finalidade;
use App\Models\PlanoConta;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * ETAPA 3 do plano de composição de taxas (docs/migration/05-plano-composicao-taxas.md).
 *
 * Transforma as 482 taxas de R$ 150,00 (competências 2024-10 a 2026-09) em
 * 482 itens de R$ 100,00 (taxa condominial) + 482 itens de R$ 50,00 (taxa para
 * pintura do prédio), e faz o backfill de um item único nas demais taxas para
 * que a invariante do modelo valha globalmente:
 *
 *     valor_original = SUM(itens_taxa_condominial.valor)
 *
 * PROPRIEDADE DE SEGURANÇA: a operação é ADITIVA. `valor_original`,
 * `valor_desconto`, `valor_acrescimo`, `status`, `pagamentos` e `pagamento_taxa`
 * NUNCA são tocados — os itens criados somam exatamente o valor que já estava
 * lá. É isso que torna seguro decompor as taxas já pagas ou parciais.
 *
 * Idempotente (a unique uk_item_taxa_descricao + a checagem de itens por taxa
 * garantem que rodar duas vezes não duplica nem altera nada) e reversível
 * (--reverter, via manifesto gravado em storage/app/golden/composicao/).
 *
 *   php artisan taxas:decompor-composicao --dry-run
 *   php artisan taxas:decompor-composicao
 *   php artisan taxas:decompor-composicao --reverter
 */
class DecomporComposicaoTaxas extends Command
{
    private const VALOR_COMPOSTO = '150.00';

    private const VALOR_ORDINARIA = '100.00';

    private const VALOR_PINTURA = '50.00';

    private const DESC_ORDINARIA = 'Taxa condominial';

    private const DESC_PINTURA = 'Taxa para pintura do prédio';

    private const FINALIDADE_CUSTEIO = 'Custeio ordinário';

    private const FINALIDADE_PINTURA = 'Pintura do prédio';

    private const FINALIDADE_BOMBA = 'Conserto da bomba';

    /** Grafias encontradas no banco para o rendimento da poupança (D-05) */
    private const DESCRICOES_RENDIMENTO = ['Rendimento da conta', 'Rendimentos da conta'];

    protected $signature = 'taxas:decompor-composicao
        {--dry-run : Executa e desfaz no fim, apenas para conferir o relatório}
        {--reverter : Desfaz uma execução anterior a partir do manifesto}
        {--manifesto= : Caminho do manifesto (default: storage/app/golden/composicao/decomposicao-manifesto.json)}';

    protected $description = 'Etapa 3: decompõe as taxas de 150,00 em itens (100 + 50) e faz o backfill da composição';

    /** @var list<string> */
    private array $excecoes = [];

    /** @var array{id: int, finalidade_id_anterior: ?int, valor_por_unidade_anterior: ?string}|null */
    private ?array $campanhaAnterior = null;

    /** @var array<string, int> */
    private array $contadores = [
        'finalidades_criadas' => 0,
        'taxas_decompostas' => 0,
        'itens_criados' => 0,
        'taxas_backfill' => 0,
        'taxas_ja_com_itens' => 0,
        'lancamentos_afetados' => 0,
    ];

    public function handle(): int
    {
        $manifesto = $this->option('manifesto')
            ?: storage_path('app/golden/composicao/decomposicao-manifesto.json');

        if ($this->option('reverter')) {
            return $this->reverter($manifesto);
        }

        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            $registro = $this->executar();
            $this->conferirInvariante();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Abortado sem gravar nada: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            DB::rollBack();
            $this->relatar();
            $this->warn('DRY-RUN: nada foi gravado.');

            return self::SUCCESS;
        }

        DB::commit();

        $this->relatar();

        // Execução sem efeito (idempotência) não pode sobrescrever o manifesto:
        // isso apagaria o registro de reversão da execução que de fato gravou.
        if ($this->contadores['itens_criados'] === 0 && $this->contadores['lancamentos_afetados'] === 0) {
            $this->info('Nada a fazer — a composição já estava aplicada. Manifesto anterior preservado.');

            return self::SUCCESS;
        }

        $this->gravarManifesto($manifesto, $registro);
        $this->info('Manifesto de reversão em '.$manifesto);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed> registro para o manifesto de reversão
     */
    private function executar(): array
    {
        $condominioId = (int) (Condominio::query()->value('id')
            ?? throw new RuntimeException('Nenhum condomínio cadastrado.'));

        [$planoOrdinaria, $planoPintura] = $this->planosDeConta($condominioId);

        $finalidades = $this->garantirFinalidades($condominioId);
        $campanha = $this->ajustarCampanha($finalidades[self::FINALIDADE_PINTURA]->id);

        // Timestamp único de toda a execução: é o marcador que permite ao
        // --reverter distinguir os itens do ETL dos criados depois pela aplicação
        $marcador = now();

        $itemIdAntes = (int) (DB::table('itens_taxa_condominial')->max('id') ?? 0);

        $this->levantarExcecoes();

        $this->decompor($planoOrdinaria, $planoPintura, $finalidades, $campanha, $marcador);
        $this->backfill($planoOrdinaria, $finalidades[self::FINALIDADE_CUSTEIO]->id, $marcador);

        $lancamentos = $this->afetarLancamentos($finalidades);

        return [
            'executado_em' => $marcador->toDateTimeString(),
            'item_id_minimo' => $itemIdAntes + 1,
            'finalidades_criadas' => array_values(array_map(
                fn (Finalidade $f): int => $f->id,
                array_filter($finalidades, fn (Finalidade $f): bool => $f->wasRecentlyCreated)
            )),
            'campanha' => $this->campanhaAnterior,
            'lancamentos' => $lancamentos,
        ];
    }

    /**
     * @return array{PlanoConta, PlanoConta}
     */
    private function planosDeConta(int $condominioId): array
    {
        $buscar = fn (string $codigo): PlanoConta => PlanoConta::query()
            ->where('condominio_id', $condominioId)
            ->where('codigo', $codigo)
            ->first() ?? throw new RuntimeException("Plano de contas {$codigo} não encontrado.");

        // R-001 Receita de Taxa Condominial · R-002 Cobranças Extraordinárias
        return [$buscar('R-001'), $buscar('R-002')];
    }

    /**
     * @return array<string, Finalidade>
     */
    private function garantirFinalidades(int $condominioId): array
    {
        $descricoes = [
            self::FINALIDADE_CUSTEIO => [
                'descricao' => 'Despesas correntes de manutenção e administração do condomínio.',
                'vigencia_inicio' => null,
                'vigencia_fim' => null,
            ],
            self::FINALIDADE_PINTURA => [
                'descricao' => 'Arrecadação destinada a viabilizar a pintura do prédio.',
                'vigencia_inicio' => '2024-04-30',
                'vigencia_fim' => '2026-12-31',
            ],
            self::FINALIDADE_BOMBA => [
                'descricao' => 'Contribuições destinadas ao conserto da bomba d\'água.',
                'vigencia_inicio' => null,
                'vigencia_fim' => null,
            ],
        ];

        $saida = [];

        foreach ($descricoes as $nome => $atributos) {
            $finalidade = Finalidade::query()->firstOrCreate(
                ['condominio_id' => $condominioId, 'nome' => $nome],
                $atributos + ['ativa' => true],
            );

            if ($finalidade->wasRecentlyCreated) {
                $this->contadores['finalidades_criadas']++;
            }

            $saida[$nome] = $finalidade;
        }

        return $saida;
    }

    /**
     * A campanha da pintura passa a carregar a finalidade e o valor cobrado por
     * competência (D-04) — é ela que gerará os itens dos anos futuros.
     *
     * Os valores anteriores ficam em $this->campanhaAnterior porque `update()`
     * ressincroniza os atributos originais do Model: ler getOriginal() depois
     * devolveria o valor NOVO e a reversão não restauraria nada.
     */
    private function ajustarCampanha(int $finalidadeId): ?CobrancaExtraordinaria
    {
        $campanha = CobrancaExtraordinaria::query()
            ->where('nome', 'like', '%pintura%')
            ->orderBy('id')
            ->first();

        if ($campanha === null) {
            $this->excecoes[] = 'Nenhuma cobrança extraordinária de pintura encontrada — '
                .'os itens de pintura ficarão sem origem rastreada.';

            return null;
        }

        $this->campanhaAnterior = [
            'id' => (int) $campanha->id,
            'finalidade_id_anterior' => $campanha->finalidade_id,
            'valor_por_unidade_anterior' => $campanha->valor_por_unidade,
        ];

        $campanha->update([
            'finalidade_id' => $finalidadeId,
            'valor_por_unidade' => self::VALOR_PINTURA,
        ]);

        return $campanha;
    }

    /**
     * Inconsistência N-02: linhas do pivô da campanha apontando para taxas que
     * NÃO têm os 50,00 embutidos. Não decompomos automaticamente (mudaria o
     * valor devido de uma taxa existente, coisa que este comando nunca faz) —
     * a taxa é reportada para decisão manual.
     */
    private function levantarExcecoes(): void
    {
        if (! Schema::hasTable('cobranca_extraordinaria_taxa')) {
            return;
        }

        DB::table('cobranca_extraordinaria_taxa as p')
            ->join('taxas_condominiais as t', 't.id', '=', 'p.taxa_condominial_id')
            ->whereNull('t.deleted_at')
            ->where('t.valor_original', '<>', self::VALOR_COMPOSTO)
            ->orderBy('t.id')
            ->get(['t.id', 't.unidade_id', 't.competencia_ano', 't.competencia_mes', 't.valor_original', 'p.valor'])
            ->each(function (object $l): void {
                $this->excecoes[] = sprintf(
                    'Taxa #%d (unidade %d, %02d/%d) tem valor_original %s mas o pivô da campanha registra %s — '
                    .'decidir manualmente se a cobrança dos %s falta ou se a linha do pivô é indevida.',
                    $l->id, $l->unidade_id, $l->competencia_mes, $l->competencia_ano,
                    $l->valor_original, $l->valor, $l->valor,
                );
            });
    }

    /**
     * As 482 taxas de 150,00 → item ordinário de 100,00 (ordem 0) + item de
     * pintura de 50,00 (ordem 1). Soma idêntica ao valor_original atual.
     *
     * @param  array<string, Finalidade>  $finalidades
     */
    private function decompor(
        PlanoConta $planoOrdinaria,
        PlanoConta $planoPintura,
        array $finalidades,
        ?CobrancaExtraordinaria $campanha,
        CarbonInterface $marcador,
    ): void {
        DB::table('taxas_condominiais')
            ->whereNull('deleted_at')
            ->where('valor_original', self::VALOR_COMPOSTO)
            ->orderBy('id')
            ->select('id')
            ->chunk(500, function ($taxas) use ($planoOrdinaria, $planoPintura, $finalidades, $campanha, $marcador): void {
                $linhas = [];

                foreach ($taxas as $taxa) {
                    if ($this->jaTemItens((int) $taxa->id)) {
                        continue;
                    }

                    $linhas[] = $this->linhaItem((int) $taxa->id, [
                        'plano_conta_id' => $planoOrdinaria->id,
                        'finalidade_id' => $finalidades[self::FINALIDADE_CUSTEIO]->id,
                        'descricao' => self::DESC_ORDINARIA,
                        'valor' => self::VALOR_ORDINARIA,
                        'ordem' => 0,
                    ], $marcador);

                    $linhas[] = $this->linhaItem((int) $taxa->id, [
                        'plano_conta_id' => $planoPintura->id,
                        'finalidade_id' => $finalidades[self::FINALIDADE_PINTURA]->id,
                        'descricao' => self::DESC_PINTURA,
                        'valor' => self::VALOR_PINTURA,
                        'ordem' => 1,
                        'origem_type' => $campanha === null ? null : CobrancaExtraordinaria::class,
                        'origem_id' => $campanha?->id,
                    ], $marcador);

                    $this->contadores['taxas_decompostas']++;
                }

                $this->inserir($linhas);
            });
    }

    /**
     * Demais taxas → um único item ordinário com o valor_original, para que a
     * invariante valha globalmente. Inclui as competências de valor 0,00
     * (item de 0,00 preserva a semântica de competência isenta).
     */
    private function backfill(PlanoConta $plano, int $finalidadeCusteioId, CarbonInterface $marcador): void
    {
        DB::table('taxas_condominiais')
            ->whereNull('deleted_at')
            ->where('valor_original', '<>', self::VALOR_COMPOSTO)
            ->orderBy('id')
            ->select('id', 'valor_original')
            ->chunk(500, function ($taxas) use ($plano, $finalidadeCusteioId, $marcador): void {
                $linhas = [];

                foreach ($taxas as $taxa) {
                    if ($this->jaTemItens((int) $taxa->id)) {
                        continue;
                    }

                    $linhas[] = $this->linhaItem((int) $taxa->id, [
                        'plano_conta_id' => $plano->id,
                        'finalidade_id' => $finalidadeCusteioId,
                        'descricao' => self::DESC_ORDINARIA,
                        'valor' => number_format((float) $taxa->valor_original, 2, '.', ''),
                        'ordem' => 0,
                    ], $marcador);

                    $this->contadores['taxas_backfill']++;
                }

                $this->inserir($linhas);
            });
    }

    /**
     * Finalidade nos lançamentos financeiros de receita (D-05 + premissa da
     * §2 do plano). Só toca em linhas ainda sem finalidade.
     *
     * @param  array<string, Finalidade>  $finalidades
     * @return list<array{id: int, finalidade_id_anterior: null}>
     */
    private function afetarLancamentos(array $finalidades): array
    {
        $mapa = [
            self::FINALIDADE_PINTURA => fn ($q) => $q->whereIn('descricao', self::DESCRICOES_RENDIMENTO),
            self::FINALIDADE_BOMBA => fn ($q) => $q->where('descricao', 'like', '%bomba%'),
        ];

        $registro = [];

        foreach ($mapa as $nomeFinalidade => $filtro) {
            $base = fn () => $filtro(
                DB::table('lancamentos_financeiros')
                    ->whereNull('deleted_at')
                    ->where('natureza', 'receita')
                    ->whereNull('finalidade_id')
            );

            $ids = $base()->orderBy('id')->pluck('id')->all();

            if ($ids === []) {
                continue;
            }

            DB::table('lancamentos_financeiros')
                ->whereIn('id', $ids)
                ->update(['finalidade_id' => $finalidades[$nomeFinalidade]->id, 'updated_at' => now()]);

            foreach ($ids as $id) {
                $registro[] = ['id' => (int) $id, 'finalidade_id_anterior' => null];
            }

            $this->contadores['lancamentos_afetados'] += count($ids);
        }

        return $registro;
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function linhaItem(int $taxaId, array $atributos, CarbonInterface $marcador): array
    {
        return $atributos + [
            'taxa_condominial_id' => $taxaId,
            'finalidade_id' => null,
            'origem_type' => null,
            'origem_id' => null,
            'created_at' => $marcador,
            'updated_at' => $marcador,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     */
    private function inserir(array $linhas): void
    {
        if ($linhas === []) {
            return;
        }

        DB::table('itens_taxa_condominial')->insert($linhas);
        $this->contadores['itens_criados'] += count($linhas);
    }

    private function jaTemItens(int $taxaId): bool
    {
        $tem = DB::table('itens_taxa_condominial')
            ->where('taxa_condominial_id', $taxaId)
            ->whereNull('deleted_at')
            ->exists();

        if ($tem) {
            $this->contadores['taxas_ja_com_itens']++;
        }

        return $tem;
    }

    /**
     * Invariante §3.4 — roda dentro da transação: qualquer divergência aborta
     * a execução inteira antes do commit.
     */
    private function conferirInvariante(): void
    {
        $divergentes = DB::table('taxas_condominiais as t')
            ->whereNull('t.deleted_at')
            ->whereRaw(
                't.valor_original <> COALESCE((SELECT SUM(i.valor) FROM itens_taxa_condominial i
                    WHERE i.taxa_condominial_id = t.id AND i.deleted_at IS NULL), 0)'
            )
            ->count();

        if ($divergentes > 0) {
            throw new RuntimeException(
                "{$divergentes} taxa(s) ficariam com valor_original diferente da soma dos itens."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $registro
     */
    private function gravarManifesto(string $caminho, array $registro): void
    {
        $dir = dirname($caminho);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $caminho,
            json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Desfaz uma execução: apaga fisicamente apenas os itens criados por ela
     * (id >= mínimo E created_at igual ao marcador da execução), devolve os
     * lançamentos ao estado anterior e restaura os campos da campanha.
     * As finalidades criadas só são removidas se não sobrou nada apontando
     * para elas.
     */
    private function reverter(string $caminho): int
    {
        if (! is_file($caminho)) {
            $this->error("Manifesto não encontrado em {$caminho} — nada a reverter.");

            return self::FAILURE;
        }

        /** @var array<string, mixed> $registro */
        $registro = json_decode((string) file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($registro): void {
            $itens = DB::table('itens_taxa_condominial')
                ->where('id', '>=', $registro['item_id_minimo'])
                ->where('created_at', $registro['executado_em'])
                ->delete();

            $this->line("  - {$itens} item(ns) removido(s)");

            foreach ($registro['lancamentos'] ?? [] as $lancamento) {
                DB::table('lancamentos_financeiros')
                    ->where('id', $lancamento['id'])
                    ->update(['finalidade_id' => $lancamento['finalidade_id_anterior']]);
            }

            $this->line('  - '.count($registro['lancamentos'] ?? []).' lançamento(s) restaurado(s)');

            if (($registro['campanha'] ?? null) !== null) {
                DB::table('cobrancas_extraordinarias')
                    ->where('id', $registro['campanha']['id'])
                    ->update([
                        'finalidade_id' => $registro['campanha']['finalidade_id_anterior'],
                        'valor_por_unidade' => $registro['campanha']['valor_por_unidade_anterior'],
                    ]);

                $this->line('  - campanha restaurada');
            }

            foreach ($registro['finalidades_criadas'] ?? [] as $finalidadeId) {
                $emUso = DB::table('itens_taxa_condominial')->where('finalidade_id', $finalidadeId)->exists()
                    || DB::table('lancamentos_financeiros')->where('finalidade_id', $finalidadeId)->exists()
                    || DB::table('cobrancas_extraordinarias')->where('finalidade_id', $finalidadeId)->exists();

                if (! $emUso) {
                    DB::table('finalidades')->where('id', $finalidadeId)->delete();
                    $this->line("  - finalidade #{$finalidadeId} removida");
                } else {
                    $this->warn("  - finalidade #{$finalidadeId} mantida (ainda em uso)");
                }
            }
        });

        @unlink($caminho);

        $this->info('Reversão concluída. Confira com `composicao:snapshot --comparar`.');

        return self::SUCCESS;
    }

    private function relatar(): void
    {
        $this->table(
            ['Ação', 'Quantidade'],
            array_map(
                fn (string $chave, int $valor): array => [$chave, (string) $valor],
                array_keys($this->contadores),
                array_values($this->contadores),
            )
        );

        if ($this->excecoes === []) {
            $this->info('Nenhuma exceção — nada exige decisão manual.');

            return;
        }

        $this->warn(count($this->excecoes).' exceção(ões) exigindo decisão manual:');

        foreach ($this->excecoes as $excecao) {
            $this->line('  * '.$excecao);
        }
    }
}
