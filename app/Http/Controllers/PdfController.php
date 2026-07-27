<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NaturezaLancamento;
use App\Models\CobrancaExtraordinaria;
use App\Models\ItemTaxa;
use App\Models\LancamentoFinanceiro;
use App\Models\Pagamento;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Services\CorrecaoMonetariaService;
use App\Services\RateioPorFinalidadeService;
use App\Support\ConfiguracoesCondominio;
use App\Support\DinheiroBr;
use App\Support\ResumoFinanceiro;
use App\Support\TextoPtBr;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDFs do modelo novo (substitui PdfController no cutover) — mesma paridade de
 * CONTEÚDO com o legado, lendo taxas/pagamentos/lançamentos. Reutiliza os
 * templates de dados agregados do PdfController (recibo-mensalidade,
 * dividas-imovel, dividas-consolidado, resumo-historico); os templates
 * acoplados a Models antigos ganham versões `-novo`.
 */
class PdfController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CorrecaoMonetariaService $correcaoService) {}

    /**
     * Recibo de taxa (paridade com pdf.mensalidades.recibo).
     */
    public function reciboTaxa(TaxaCondominial $taxa): Response
    {
        $valorPago = (float) ($taxa->pagamentoTaxas()->sum('valor_aplicado') ?: 0);

        abort_unless(
            $valorPago > 0,
            422,
            'Essa taxa ainda não foi paga. Vencimento: '.$taxa->vencimento?->format('d/m/Y'),
        );

        $ultimoPagamento = $taxa->pagamentos()->max('data_pagamento');

        abort_unless(
            $ultimoPagamento !== null,
            422,
            'Não foi possível determinar a data de pagamento da taxa',
        );

        $dataPagamento = Carbon::parse($ultimoPagamento);

        $taxa->load('unidade');
        $responsavel = $taxa->unidade->vinculos()
            ->whereNull('data_fim')
            ->orderByDesc('responsavel_financeiro')
            ->with('pessoa')
            ->first();

        $nome = $responsavel?->pessoa->nome ?? 'Não informado';

        // Texto principal do recibo — paridade literal com o legado
        $recibo = "Recebemos de {$nome} a quantia de R$ ".
            DinheiroBr::formatar($valorPago).
            ' ('.TextoPtBr::valorPorExtenso($valorPago).') referente ao pagamento do condomínio do mês '.
            TextoPtBr::meses()[(int) $taxa->competencia_mes].' de '.$taxa->competencia_ano.".\n";

        $complementoTextoDivida = null;

        if (((float) $taxa->valor_original - $valorPago) > 0) {
            $valorRestante = (float) $taxa->valor_original - $valorPago;
            $recibo .= 'Resta ainda a quantia de R$ '.
                DinheiroBr::formatar($valorRestante).
                ' ('.TextoPtBr::valorPorExtenso($valorRestante).') para completar o pagamento total.';
            $complementoTextoDivida = 'ainda';
        }

        // Dívidas anteriores corrigidas — mesma regra do legado: a data-base é
        // a data de pagamento DE CADA taxa em aberto (null => hoje)
        $taxasAnteriores = TaxaCondominial::query()
            ->where('unidade_id', $taxa->unidade_id)
            ->where('vencimento', '<', $dataPagamento->toDateString())
            ->withSum('pagamentoTaxas as pago', 'valor_aplicado')
            ->withMax('pagamentos as ultimo_pg', 'data_pagamento')
            ->get();

        $dividasCorrigidas = $taxasAnteriores->sum(function (TaxaCondominial $t): float {
            $saldo = (float) $t->valorDevido() - (float) ($t->pago ?? 0);

            if ($saldo <= 0) {
                return 0.0;
            }

            return $this->correcaoService->corrigirTaxa(
                $saldo,
                $t->vencimento,
                $t->ultimo_pg !== null ? Carbon::parse($t->ultimo_pg) : now(),
            );
        });

        if ($dividasCorrigidas > 0) {
            $valorFormatado = DinheiroBr::formatar((float) $dividasCorrigidas);
            $recibo .= " Identificamos {$complementoTextoDivida} o valor de R$ {$valorFormatado} em dívidas atrasadas (valores corrigidos pelo IPCA).";
        }

        $cidade = ConfiguracoesCondominio::get('cidade_recibo', 'Rio Branco');

        $pdfName = 'recibo_'.
            $taxa->unidade->identificacao.'_'.
            $taxa->competencia_ano.'_'.
            str_pad((string) $taxa->competencia_mes, 2, '0', STR_PAD_LEFT).'.pdf';

        $pdf = app('dompdf.wrapper')->loadView('pdf.recibo-mensalidade', [
            'title' => ConfiguracoesCondominio::nomeCondominio(),
            'subTitle' => ConfiguracoesCondominio::subtituloRecibo(),
            'recibo' => $recibo,
            'data' => $cidade.', '.TextoPtBr::dataExtenso($dataPagamento),
        ]);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream($pdfName);
    }

    /**
     * Recibo de pagamento (paridade com pdf.pagamentos.recibo).
     */
    public function reciboPagamento(Pagamento $pagamento): Response
    {
        $this->authorize('emitirRecibo', $pagamento);

        $pagamento->load(['pessoa', 'unidade', 'taxasCondominiais']);

        $pdf = app('dompdf.wrapper')
            ->loadView('pdf.recibo-pagamento', ['pagamento' => $pagamento])
            ->setPaper('a4', 'portrait');

        return $pdf->stream('recibo-pagamento-'.$pagamento->id.'.pdf');
    }

    /**
     * Inadimplência por unidade (paridade com pdf.dividas.por-imovel).
     */
    public function inadimplenciaPorUnidade(Unidade $unidade): Response
    {
        $taxas = TaxaCondominial::query()
            ->where('unidade_id', $unidade->id)
            ->emAberto()
            ->with('itens')
            ->orderBy('vencimento')
            ->get();

        $resumoMensal = $taxas
            ->groupBy(fn (TaxaCondominial $t) => $t->vencimento->year)
            ->map(function ($grupoAno) {
                return collect(range(1, 12))->map(function (int $mes) use ($grupoAno): array {
                    $taxa = $grupoAno->first(fn (TaxaCondominial $t) => $t->vencimento->month === $mes);

                    if (! $taxa) {
                        return ['valor_num' => 0, 'valor_fmt' => '-', 'class' => ''];
                    }

                    $valorCorrigido = $this->correcaoService->corrigirTaxa(
                        (float) $taxa->valor_liquido,
                        $taxa->vencimento,
                    );

                    return [
                        'valor_num' => $valorCorrigido,
                        'valor_fmt' => DinheiroBr::formatar($valorCorrigido),
                        'class' => 'text-danger',
                    ];
                });
            });

        $memoriaCalculo = $taxas
            ->groupBy(fn (TaxaCondominial $t) => $t->vencimento->year)
            ->map(function ($grupoAno) {
                return $grupoAno->map(function (TaxaCondominial $t): array {
                    return $this->correcaoService->memoriaCalculo(
                        (float) $t->valor_liquido,
                        $t->vencimento,
                    ) + [
                        'competencia' => $t->vencimento->format('m/Y'),
                        // Discriminação da composição (05-plano-composicao-taxas.md
                        // Etapa 5): é o que dá transparência ao condômino sobre o
                        // que compõe o valor cobrado na competência
                        'composicao' => $t->itens
                            ->map(fn ($item): string => $item->descricao.': '.DinheiroBr::formatar($item->valor))
                            ->all(),
                    ];
                });
            });

        $pdf = app('dompdf.wrapper')->loadView('pdf.dividas-imovel', [
            'title' => 'Dívidas da Unidade',
            'subTitle' => $unidade->identificacao,
            'mensalidades' => $resumoMensal,
            'memoriaCalculo' => $memoriaCalculo,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('dividas_unidade_'.$unidade->id.'.pdf');
    }

    /**
     * Inadimplência consolidada (paridade com pdf.dividas.consolidado).
     */
    public function inadimplenciaConsolidada(): Response
    {
        $dividas = [];
        $anos = [];

        $taxas = TaxaCondominial::with('unidade')->emAberto()->orderBy('unidade_id')->get();

        foreach ($taxas as $t) {
            $ano = $t->vencimento->year;
            $identificacao = $t->unidade->identificacao;

            $valorCorrigido = $this->correcaoService->corrigirTaxa(
                (float) $t->valor_liquido,
                $t->vencimento,
            );

            $dividas[$identificacao][$ano] = ($dividas[$identificacao][$ano] ?? 0) + $valorCorrigido;
            $anos[$ano] = $ano;
        }

        ksort($anos);

        $pdf = app('dompdf.wrapper')->loadView('pdf.dividas-consolidado', [
            'title' => 'Dívidas Atualizadas',
            'subTitle' => 'Valores corrigidos pelo IPCA',
            'dividas' => $dividas,
            'anos' => $anos,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('dividas_atualizadas.pdf');
    }

    /**
     * Despesas (lançamentos) por período (paridade com pdf.despesas).
     */
    public function despesasPorPeriodo(Request $request): Response
    {
        $dados = $request->validate([
            'data_inicial' => ['required', 'date'],
            'data_final' => ['nullable', 'date', 'after_or_equal:data_inicial'],
        ], [
            'data_inicial.required' => 'Data inicial inválida!',
        ]);

        $dataInicial = $dados['data_inicial'];
        $dataFinal = $dados['data_final'] ?? date('Y-m-d');

        $despesas = LancamentoFinanceiro::with('planoConta')
            ->where('natureza', 'despesa')
            ->where('data_lancamento', '>=', $dataInicial)
            ->where('data_lancamento', '<=', $dataFinal)
            ->orderBy('data_lancamento', 'DESC')
            ->get();

        $bloco = ConfiguracoesCondominio::get('identificacao_bloco', 'Bloco R-04');

        $pdf = app('dompdf.wrapper')->loadView('pdf.despesas-periodo', [
            'title' => ConfiguracoesCondominio::nomeCondominio(),
            'subTitle' => "Despesas - {$bloco} - De: ".date('d/m/Y', strtotime($dataInicial)).' a '.date('d/m/Y', strtotime($dataFinal)),
            'despesas' => $despesas,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('despesas_'.date('Y-m-d', strtotime($dataInicial)).'_'.date('Y-m-d', strtotime($dataFinal)).'.pdf');
    }

    /**
     * Resumo histórico (paridade com pdf.resumo).
     */
    public function resumoHistorico(): Response
    {
        $unidades = Unidade::orderBy('identificacao')->pluck('identificacao', 'identificacao');

        $aplicacoes = ResumoFinanceiro::aplicacoesContabilizadas()
            ->join('unidades', 'unidades.id', '=', 'taxas_condominiais.unidade_id')
            ->selectRaw(ResumoFinanceiro::anoSql('pagamentos_novo.data_pagamento').' as ano, unidades.identificacao, SUM(pagamento_taxa.valor_aplicado) as total')
            ->groupBy('ano', 'unidades.identificacao')
            ->orderBy('ano')
            ->get();

        $lancamentos = LancamentoFinanceiro::query()
            ->selectRaw(ResumoFinanceiro::anoSql('data_lancamento').' as ano, natureza, SUM(valor) as total')
            ->groupBy('ano', 'natureza')
            ->orderBy('ano')
            ->get();

        $resumo = [];
        $totalUnidade = [];

        foreach ($aplicacoes as $linha) {
            $resumo[(string) $linha->ano][$linha->identificacao] =
                ($resumo[(string) $linha->ano][$linha->identificacao] ?? 0) + (float) $linha->total;
            $totalUnidade[$linha->identificacao] = ($totalUnidade[$linha->identificacao] ?? 0) + (float) $linha->total;
        }

        foreach ($lancamentos as $linha) {
            $chave = $linha->natureza === NaturezaLancamento::Despesa ? 'despesas' : 'receita';
            $resumo[(string) $linha->ano][$chave] = ($resumo[(string) $linha->ano][$chave] ?? 0) + (float) $linha->total;
        }

        ksort($resumo);

        [$reservaTaxas, $reservaReceitas] = $this->apurarReservaCobrancas();

        // Mesma segregação da tela: o saldo das finalidades vinculadas não está
        // disponível para custeio ordinário.
        $rateio = app(RateioPorFinalidadeService::class);
        $vinculadas = $rateio->vinculadoPorFinalidade();

        $bloco = ConfiguracoesCondominio::get('identificacao_bloco', 'Bloco R-04');
        $url = ConfiguracoesCondominio::get('url_sistema', 'http://r4.condominio.space/');

        $pdf = app('dompdf.wrapper')->loadView('pdf.resumo-historico', [
            'title' => ConfiguracoesCondominio::nomeCondominio(),
            'subTitle' => "Resumo das receitas e despesas - {$bloco} - {$url}",
            'resumo' => $resumo,
            'imoveis' => $unidades,
            'total_imovel' => $totalUnidade,
            'poupanca' => $reservaTaxas,
            'juros_poupanca' => $reservaReceitas,
            'vinculadas' => $vinculadas,
            'saldo_vinculado' => (float) $rateio->somarSaldoVinculado($vinculadas),
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('resumo_bloco_r4.pdf');
    }

    /**
     * Resumo por intervalo (paridade com pdf.resumo.intervalo, EX-04 mantido).
     */
    public function resumoIntervalo(Request $request): Response
    {
        $de = $request->query('de') ?: date('Y-m-01');
        $ate = $request->query('ate') ?: date('Y-m-t');

        $saldo = ResumoFinanceiro::saldoAnterior($de);

        $aplicacoes = ResumoFinanceiro::aplicacoesContabilizadas()
            ->join('unidades', 'unidades.id', '=', 'taxas_condominiais.unidade_id')
            ->whereBetween('pagamentos_novo.data_pagamento', [$de, $ate])
            ->select([
                'pagamento_taxa.valor_aplicado',
                'pagamentos_novo.data_pagamento',
                'unidades.identificacao',
                'taxas_condominiais.competencia_mes',
                'taxas_condominiais.competencia_ano',
            ])
            ->orderBy('pagamentos_novo.data_pagamento')
            ->get();

        $despesas = LancamentoFinanceiro::where('natureza', 'despesa')
            ->whereBetween('data_lancamento', [$de, $ate])->orderBy('data_lancamento')->get();
        $receitas = LancamentoFinanceiro::where('natureza', 'receita')
            ->whereBetween('data_lancamento', [$de, $ate])->orderBy('data_lancamento')->get();

        [$reservaTaxas, $reservaReceitas] = $this->apurarReservaCobrancas();

        $pdf = app('dompdf.wrapper')->loadView('pdf.resumo-intervalo', [
            'saldo' => $saldo,
            'aplicacoes' => $aplicacoes,
            'despesas' => $despesas,
            'receitas' => $receitas,
            'poupanca' => $reservaTaxas,
            'juros_poupanca' => $reservaReceitas,
            'de' => $de,
            'ate' => $ate,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('resumo_intervalo'.$de.'_a_'.$ate.'_bloco_r4.pdf');
    }

    /**
     * Reserva por cobranças extraordinárias (DEV-12): itens de taxa gerados
     * pelas cobranças + lançamentos de receita com origem em cobrança.
     * Etapa 6 de 05-plano-composicao-taxas.md: a fonte deixou de ser o pivô
     * cobranca_extraordinaria_taxa e passou a ser itens_taxa_condominial.
     *
     * @return array{0: float, 1: float}
     */
    private function apurarReservaCobrancas(): array
    {
        $reservaTaxas = (float) ItemTaxa::query()
            ->where('origem_type', CobrancaExtraordinaria::class)
            ->sum('valor');
        $reservaReceitas = (float) LancamentoFinanceiro::query()
            ->whereNotNull('origem_id')
            ->where('natureza', 'receita')
            ->sum('valor');

        return [$reservaTaxas, $reservaReceitas];
    }
}
