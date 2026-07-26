<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\StatusTaxa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3 — validação profunda da remodelagem (04-plano-migracao.md), somente
 * leitura. Vai além das somas globais do orquestrador: reconcilia POR UNIDADE
 * e POR COMPETÊNCIA (totais globais podem bater com erros que se cancelam),
 * confere a cobertura de pagamento taxa a taxa, compara o status derivado com
 * a semântica do legado e checa integridade dos vínculos.
 *
 * Exit code de falha se qualquer verificação obrigatória divergir.
 */
class ValidarRemodelagem extends Command
{
    protected $signature = 'migrar:validar-remodelagem';

    protected $description = 'Remodelagem: validação profunda (por unidade, por competência, cobertura e integridade)';

    private int $falhas = 0;

    public function handle(): int
    {
        $this->porUnidade();
        $this->porCompetencia();
        $this->coberturaPagamentos();
        $this->statusVersusLegado();
        $this->integridade();

        $this->newLine();

        if ($this->falhas > 0) {
            $this->error("Validação profunda: {$this->falhas} verificação(ões) com divergência.");

            return self::FAILURE;
        }

        $this->info('Validação profunda: todas as verificações OK.');

        return self::SUCCESS;
    }

    private function porUnidade(): void
    {
        $antigo = DB::table('mensalidades')
            ->select('imovel_id', DB::raw('SUM(valor) v'), DB::raw('SUM(desconto) d'), DB::raw('SUM(acrescimo) a'), DB::raw('COUNT(*) c'))
            ->groupBy('imovel_id')->get()->keyBy('imovel_id');

        $novo = DB::table('taxas_condominiais')
            ->select('unidade_id', DB::raw('SUM(valor_original) v'), DB::raw('SUM(valor_desconto) d'), DB::raw('SUM(valor_acrescimo) a'), DB::raw('COUNT(*) c'))
            ->groupBy('unidade_id')->get()->keyBy('unidade_id');

        $divergentes = 0;

        foreach ($antigo as $imovelId => $a) {
            $n = $novo->get($imovelId);

            if ($n === null
                || (int) $a->c !== (int) $n->c
                || bccomp((string) $a->v, (string) $n->v, 2) !== 0
                || bccomp((string) $a->d, (string) $n->d, 2) !== 0
                || bccomp((string) $a->a, (string) $n->a, 2) !== 0) {
                $divergentes++;
                $this->error("  Unidade {$imovelId}: taxas divergem do legado (contagem/valor/desconto/acréscimo).");
            }
        }

        $this->reportar('Reconciliação de taxas por unidade ('.$antigo->count().' unidades)', $divergentes);
    }

    private function porCompetencia(): void
    {
        $antigo = DB::table('mensalidades')
            ->select('ano', 'mes', DB::raw('SUM(valor) v'), DB::raw('COUNT(*) c'))
            ->groupBy('ano', 'mes')->get()->keyBy(fn (object $r): string => "{$r->ano}-{$r->mes}");

        $novo = DB::table('taxas_condominiais')
            ->select('competencia_ano as ano', 'competencia_mes as mes', DB::raw('SUM(valor_original) v'), DB::raw('COUNT(*) c'))
            ->groupBy('competencia_ano', 'competencia_mes')->get()->keyBy(fn (object $r): string => "{$r->ano}-{$r->mes}");

        $divergentes = 0;

        foreach ($antigo as $chave => $a) {
            $n = $novo->get($chave);

            if ($n === null || (int) $a->c !== (int) $n->c || bccomp((string) $a->v, (string) $n->v, 2) !== 0) {
                $divergentes++;
                $this->error("  Competência {$chave}: valores/contagens divergem do legado.");
            }
        }

        $this->reportar('Reconciliação de taxas por competência ('.$antigo->count().' competências)', $divergentes);
    }

    private function coberturaPagamentos(): void
    {
        $somas = DB::table('pagamento_taxa')
            ->select('taxa_condominial_id', DB::raw('SUM(valor_aplicado) total'))
            ->groupBy('taxa_condominial_id')->pluck('total', 'taxa_condominial_id')->all();

        $divergentes = 0;

        DB::table('mensalidades')->select('id', 'valor_pago')->orderBy('id')->chunk(500, function ($mensalidades) use ($somas, &$divergentes): void {
            foreach ($mensalidades as $m) {
                if (bccomp((string) ($somas[$m->id] ?? '0'), (string) $m->valor_pago, 2) !== 0) {
                    $divergentes++;

                    if ($divergentes <= 10) {
                        $this->error("  Taxa {$m->id}: Σ pagamento_taxa != valor_pago do legado.");
                    }
                }
            }
        });

        $this->reportar('Cobertura de pagamento por taxa (Σ pivot == valor_pago, incl. históricos)', $divergentes);
    }

    private function statusVersusLegado(): void
    {
        // Semântica do legado (Mensalidade::status): paga quando
        // valor_pago >= valor - desconto e valor_pago > 0; parcial quando > 0.
        $legado = DB::table('mensalidades')
            ->select('id', 'valor', 'desconto', 'valor_pago')->get()->keyBy('id');

        $novo = DB::table('taxas_condominiais')->pluck('status', 'id');
        $divergentes = 0;

        foreach ($legado as $id => $m) {
            $liquido = bcsub((string) $m->valor, (string) $m->desconto, 2);
            $pago = (string) $m->valor_pago;

            $statusLegado = match (true) {
                bccomp($pago, '0', 2) > 0 && bccomp($pago, $liquido, 2) >= 0 => StatusTaxa::Pago->value,
                bccomp($pago, '0', 2) > 0 => StatusTaxa::PagoParcial->value,
                default => StatusTaxa::Aberto->value,
            };

            if (($novo[$id] ?? null) !== $statusLegado) {
                $divergentes++;

                if ($divergentes <= 10) {
                    $this->error("  Taxa {$id}: status novo '{$novo[$id]}' != semântica legada '{$statusLegado}'.");
                }
            }
        }

        // Divergência aqui só é esperada quando houver acréscimo (o novo modelo
        // considera acréscimo no valor devido; o legado não) — hoje Σ acréscimo = 0.
        $this->reportar('Status derivado vs semântica do legado ('.$legado->count().' taxas)', $divergentes);
    }

    private function integridade(): void
    {
        $unidadesSemResponsavel = DB::table('unidades')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('unidade_pessoa')
                ->whereColumn('unidade_pessoa.unidade_id', 'unidades.id')
                ->where('responsavel_financeiro', true)->whereNull('data_fim'))
            ->count();
        $this->reportar('Unidades sem responsável financeiro vigente', $unidadesSemResponsavel);

        $origensQuebradas = DB::table('lancamentos_financeiros')
            ->whereNotNull('origem_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cobrancas_extraordinarias')
                ->whereColumn('cobrancas_extraordinarias.id', 'lancamentos_financeiros.origem_id'))
            ->count();
        $this->reportar('Lançamentos com origem polimórfica quebrada', $origensQuebradas);

        $estornosInvalidos = DB::table('pagamentos_novo as e')
            ->join('pagamentos_novo as o', 'o.id', '=', 'e.estorno_de_id')
            ->where('e.valor_total', '>=', 0)
            ->count();
        $this->reportar('Estornos com valor não-negativo', $estornosInvalidos);

        // Informativo: pessoas sem nenhum vínculo com unidade (não é erro)
        $pessoasSemVinculo = DB::table('pessoas')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('unidade_pessoa')
                ->whereColumn('unidade_pessoa.pessoa_id', 'pessoas.id'))
            ->count();
        $this->line("  (info) Pessoas sem vínculo com unidade: {$pessoasSemVinculo}.");
    }

    private function reportar(string $rotulo, int $divergencias): void
    {
        if ($divergencias === 0) {
            $this->info("OK  {$rotulo}.");
        } else {
            $this->falhas++;
            $this->error("DIVERGÊNCIA  {$rotulo}: {$divergencias} caso(s).");
        }
    }
}
