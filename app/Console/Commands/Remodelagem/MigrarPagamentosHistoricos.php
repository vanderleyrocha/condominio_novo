<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\FormaPagamento;
use App\Support\Remodelagem\MapaIds;
use Illuminate\Support\Facades\DB;

/**
 * Passo 7b — pagamentos HISTÓRICOS sintetizados.
 *
 * No legado, a maioria das mensalidades pagas tem valor_pago > 0 sem nenhuma
 * linha em pagamento_mensalidades (quitações anteriores ao módulo de
 * pagamentos). Como o modelo novo deriva o status da soma de pagamento_taxa,
 * essas taxas ficariam eternamente "aberto". Este passo cria 1 pagamento
 * sintético por mensalidade cobrindo a lacuna (valor_pago - soma do pivot),
 * rastreado em migration_id_map (entidade `pagamento_historico`,
 * id_antigo = mensalidade.id).
 *
 * pessoa_id = responsável financeiro vigente da unidade;
 * data = pago_em (fallback: vencimento).
 */
class MigrarPagamentosHistoricos extends ComandoRemodelagem
{
    protected $signature = 'migrar:pagamentos-historicos {--truncar}';

    protected $description = 'Remodelagem: sintetiza pagamentos históricos (valor_pago sem pivot no legado)';

    protected function tabelasDestino(): array
    {
        return []; // acrescenta linhas a pagamentos_novo/pagamento_taxa — guarda via mapa abaixo
    }

    protected function entidadesMapa(): array
    {
        return ['pagamento_historico'];
    }

    protected function executar(): int
    {
        if (DB::table('migration_id_map')->where('entidade', 'pagamento_historico')->exists()) {
            $this->error('Pagamentos históricos já sintetizados. Rode `migrar:remodelagem` ou repita com --truncar.');

            return self::FAILURE;
        }

        $responsaveis = $this->responsaveisPorUnidade();

        /** @var array<int, string> $somasPivot soma já migrada por mensalidade (ids preservados) */
        $somasPivot = DB::table('pagamento_taxa')
            ->select('taxa_condominial_id', DB::raw('SUM(valor_aplicado) as total'))
            ->groupBy('taxa_condominial_id')
            ->pluck('total', 'taxa_condominial_id')
            ->all();

        $sintetizados = 0;
        $valorTotal = '0.00';
        $gapsNegativos = 0;

        DB::table('mensalidades')
            ->where('valor_pago', '>', 0)
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($mensalidades) use ($responsaveis, $somasPivot, &$sintetizados, &$valorTotal, &$gapsNegativos): void {
                $pares = [];

                foreach ($mensalidades as $m) {
                    $gap = bcsub((string) $m->valor_pago, (string) ($somasPivot[$m->id] ?? '0'), 2);

                    if (bccomp($gap, '0', 2) < 0) {
                        $gapsNegativos++;
                        $this->log(
                            "Mensalidade {$m->id}: pivot ({$somasPivot[$m->id]}) MAIOR que valor_pago ({$m->valor_pago}) "
                            .'— inconsistência do legado, nada sintetizado (triar manualmente).'
                        );

                        continue;
                    }

                    if (bccomp($gap, '0', 2) === 0) {
                        continue; // pivot já cobre o valor pago
                    }

                    $pessoaId = $responsaveis[$m->imovel_id]
                        ?? throw new \RuntimeException("Mensalidade {$m->id}: unidade {$m->imovel_id} sem responsável financeiro vinculado.");

                    $pagamentoId = (int) DB::table('pagamentos_novo')->insertGetId([
                        'unidade_id' => $m->imovel_id,
                        'pessoa_id' => $pessoaId,
                        'data_pagamento' => $m->pago_em ?? $m->vencimento,
                        'descricao' => "Pagamento histórico migrado (mensalidade {$m->mes}/{$m->ano})",
                        'valor_total' => $gap,
                        'forma_pagamento' => FormaPagamento::NaoInformado->value,
                        'estorno_de_id' => null,
                        'created_at' => $m->created_at,
                        'updated_at' => $m->updated_at,
                    ]);

                    DB::table('pagamento_taxa')->insert([
                        'pagamento_id' => $pagamentoId,
                        'taxa_condominial_id' => $m->id,
                        'valor_aplicado' => $gap,
                        'created_at' => $m->created_at,
                        'updated_at' => $m->updated_at,
                    ]);

                    $pares[] = ['id_antigo' => (int) $m->id, 'id_novo' => $pagamentoId];
                    $valorTotal = bcadd($valorTotal, $gap, 2);
                    $sintetizados++;
                }

                MapaIds::registrarLote('pagamento_historico', $pares);
            });

        $this->log("Pagamentos históricos sintetizados: {$sintetizados} (total R$ {$valorTotal}).");

        if ($gapsNegativos > 0) {
            $this->log("Lacunas negativas (pivot > valor_pago): {$gapsNegativos} — nada sintetizado nesses casos.");
        }

        return self::SUCCESS;
    }

    protected function truncarDestino(): void
    {
        $ids = DB::table('migration_id_map')->where('entidade', 'pagamento_historico')->pluck('id_novo');

        DB::table('pagamento_taxa')->whereIn('pagamento_id', $ids)->delete();
        DB::table('pagamentos_novo')->whereIn('id', $ids)->delete();
        MapaIds::limpar('pagamento_historico');
    }

    /**
     * Pessoa responsável financeira vigente por unidade (fallback: proprietário).
     *
     * @return array<int, int>
     */
    private function responsaveisPorUnidade(): array
    {
        $responsaveis = DB::table('unidade_pessoa')
            ->where('responsavel_financeiro', true)
            ->whereNull('data_fim')
            ->pluck('pessoa_id', 'unidade_id')
            ->all();

        $proprietarios = DB::table('unidade_pessoa')
            ->where('papel', 'proprietario')
            ->whereNull('data_fim')
            ->pluck('pessoa_id', 'unidade_id')
            ->all();

        return $responsaveis + $proprietarios;
    }
}
