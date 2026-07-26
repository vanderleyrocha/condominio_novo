<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\NaturezaLancamento;
use App\Enums\TipoPlanoConta;
use App\Models\CobrancaExtraordinaria;
use App\Support\Remodelagem\MapaIds;
use Illuminate\Support\Facades\DB;

/**
 * Passo 10 — despesa_tipos → planos_contas; despesas + receitas →
 * lancamentos_financeiros (02-mapeamento-de-para.md §8).
 *
 * Planos de receita fixos: R-001 "Receita de Taxa Condominial" (receitas sem
 * cobrança extra) e R-002 "Cobranças Extraordinárias" (receitas com
 * cobranca_extra_id, que também recebem origem polimórfica).
 * data_competencia = data_lancamento (legado só tem uma data).
 */
class MigrarLancamentosFinanceiros extends ComandoRemodelagem
{
    protected $signature = 'migrar:lancamentos-financeiros {--truncar}';

    protected $description = 'Remodelagem: despesas e receitas → lançamentos financeiros (+ planos de contas)';

    protected function tabelasDestino(): array
    {
        return ['lancamentos_financeiros', 'planos_contas'];
    }

    protected function entidadesMapa(): array
    {
        return ['plano_conta_despesa_tipo', 'lancamento_despesa', 'lancamento_receita'];
    }

    protected function executar(): int
    {
        $condominioId = $this->condominioId();

        [$mapaPlanoDespesa, $planoTaxa, $planoCobrancaExtra] = $this->criarPlanosContas($condominioId);

        $totalDespesas = $this->migrarDespesas($condominioId, $mapaPlanoDespesa);
        [$totalReceitas, $comOrigem] = $this->migrarReceitas($condominioId, $planoTaxa, $planoCobrancaExtra);

        $this->log("Lançamentos migrados: {$totalDespesas} despesas + {$totalReceitas} receitas ({$comOrigem} com origem em cobrança extraordinária).");

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, int>, 1: int, 2: int}
     */
    private function criarPlanosContas(int $condominioId): array
    {
        $mapaPlanoDespesa = [];
        $pares = [];

        foreach (DB::table('despesa_tipos')->orderBy('id')->get() as $tipo) {
            $planoId = (int) DB::table('planos_contas')->insertGetId([
                'condominio_id' => $condominioId,
                'codigo' => sprintf('D-%03d', $tipo->id),
                'descricao' => $tipo->descricao,
                'tipo' => TipoPlanoConta::Despesa->value,
                'created_at' => $tipo->created_at,
                'updated_at' => $tipo->updated_at,
            ]);
            $mapaPlanoDespesa[(int) $tipo->id] = $planoId;
            $pares[] = ['id_antigo' => (int) $tipo->id, 'id_novo' => $planoId];
        }

        MapaIds::registrarLote('plano_conta_despesa_tipo', $pares);

        $planoTaxa = (int) DB::table('planos_contas')->insertGetId([
            'condominio_id' => $condominioId,
            'codigo' => 'R-001',
            'descricao' => 'Receita de Taxa Condominial',
            'tipo' => TipoPlanoConta::Receita->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $planoCobrancaExtra = (int) DB::table('planos_contas')->insertGetId([
            'condominio_id' => $condominioId,
            'codigo' => 'R-002',
            'descricao' => 'Cobranças Extraordinárias',
            'tipo' => TipoPlanoConta::Receita->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->log('Planos de contas criados: '.count($mapaPlanoDespesa).' de despesa (despesa_tipos) + R-001/R-002 de receita.');

        return [$mapaPlanoDespesa, $planoTaxa, $planoCobrancaExtra];
    }

    /**
     * @param  array<int, int>  $mapaPlanoDespesa
     */
    private function migrarDespesas(int $condominioId, array $mapaPlanoDespesa): int
    {
        $total = 0;

        DB::table('despesas')->orderBy('id')->chunk(self::CHUNK, function ($despesas) use ($condominioId, $mapaPlanoDespesa, &$total): void {
            $pares = [];

            foreach ($despesas as $d) {
                $planoId = $mapaPlanoDespesa[$d->despesa_tipo_id]
                    ?? throw new \RuntimeException("Despesa {$d->id}: despesa_tipo {$d->despesa_tipo_id} sem plano de contas.");

                $novoId = (int) DB::table('lancamentos_financeiros')->insertGetId([
                    'condominio_id' => $condominioId,
                    'plano_conta_id' => $planoId,
                    'unidade_id' => null,
                    'data_competencia' => $d->data,
                    'data_lancamento' => $d->data,
                    'descricao' => $d->descricao,
                    'valor' => $d->valor,
                    'natureza' => NaturezaLancamento::Despesa->value,
                    'contabilizado' => $d->contabilizado,
                    'origem_type' => null,
                    'origem_id' => null,
                    'created_at' => $d->created_at,
                    'updated_at' => $d->updated_at,
                ]);
                $pares[] = ['id_antigo' => (int) $d->id, 'id_novo' => $novoId];
            }

            MapaIds::registrarLote('lancamento_despesa', $pares);
            $total += count($pares);
        });

        return $total;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function migrarReceitas(int $condominioId, int $planoTaxa, int $planoCobrancaExtra): array
    {
        $total = 0;
        $comOrigem = 0;

        DB::table('receitas')->orderBy('id')->chunk(self::CHUNK, function ($receitas) use (
            $condominioId, $planoTaxa, $planoCobrancaExtra, &$total, &$comOrigem
        ): void {
            $pares = [];

            foreach ($receitas as $r) {
                $temCobranca = $r->cobranca_extra_id !== null;

                if ($temCobranca) {
                    $comOrigem++;
                }

                $novoId = (int) DB::table('lancamentos_financeiros')->insertGetId([
                    'condominio_id' => $condominioId,
                    'plano_conta_id' => $temCobranca ? $planoCobrancaExtra : $planoTaxa,
                    'unidade_id' => null,
                    'data_competencia' => $r->data,
                    'data_lancamento' => $r->data,
                    'descricao' => $r->descricao,
                    'valor' => $r->valor,
                    'natureza' => NaturezaLancamento::Receita->value,
                    'contabilizado' => $r->contabilizado,
                    'origem_type' => $temCobranca ? CobrancaExtraordinaria::class : null,
                    'origem_id' => $temCobranca ? $r->cobranca_extra_id : null, // ids de cobranças preservados
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                ]);
                $pares[] = ['id_antigo' => (int) $r->id, 'id_novo' => $novoId];
            }

            MapaIds::registrarLote('lancamento_receita', $pares);
            $total += count($pares);
        });

        return [$total, $comOrigem];
    }
}
