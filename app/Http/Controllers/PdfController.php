<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Despesa;
use App\Models\Imovel;
use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Models\Receita;
use App\Services\CorrecaoMonetariaService;
use App\Support\DinheiroBr;
use App\Support\ParametrosCondominio;
use App\Support\TextoPtBr;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller fino dos PDFs (fluxo não-Livewire) — paridade de CONTEÚDO com o
 * legado (target_screens.md, seções pdf.*), com as deviations aprovadas
 * DEV-09/DEV-10/DEV-11/DEV-12 e a correção EX-04/DT-18.
 */
class PdfController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CorrecaoMonetariaService $correcaoService) {}

    /**
     * pdf.mensalidades.recibo — paridade com MensalidadeController::recibo() do legado.
     */
    public function reciboMensalidade(Mensalidade $mensalidade): Response
    {
        abort_unless(
            (float) $mensalidade->valor_pago > 0,
            422,
            'Essa mensalidade ainda não foi paga. Vencimento: '.$mensalidade->vencimento?->format('d/m/Y'),
        );

        abort_unless(
            $mensalidade->pago_em !== null,
            422,
            'Não foi possível determinar a data de pagamento da mensalidade',
        );

        $mensalidade->load('imovel.proprietario');

        $nome = $mensalidade->imovel->proprietario->nomeDoPagador();
        $valorPago = (float) $mensalidade->valor_pago;

        // Texto principal do recibo — paridade literal com o legado
        $recibo = "Recebemos de {$nome} a quantia de R$ ".
            DinheiroBr::formatar($valorPago).
            ' ('.TextoPtBr::valorPorExtenso($valorPago).') referente ao pagamento do condomínio do mês '.
            TextoPtBr::meses()[(int) $mensalidade->mes].' de '.$mensalidade->ano.".\n";

        $complementoTextoDivida = null;

        if (((float) $mensalidade->valor - $valorPago) > 0) {
            $valorRestante = (float) $mensalidade->valor - $valorPago;
            $recibo .= 'Resta ainda a quantia de R$ '.
                DinheiroBr::formatar($valorRestante).
                ' ('.TextoPtBr::valorPorExtenso($valorRestante).') para completar o pagamento total.';
            $complementoTextoDivida = 'ainda';
        }

        // Dívidas anteriores corrigidas: mensalidades do mesmo imóvel com
        // vencimento < pago_em do recibo. Paridade com o CÓDIGO REAL do legado
        // (MensalidadeController::recibo): a data-base é o pago_em DE CADA
        // mensalidade em aberto (null => hoje), não o pago_em do recibo —
        // confirmado na comparação golden de 2026-07-16.
        $mensalidadesAnteriores = Mensalidade::query()
            ->where('imovel_id', $mensalidade->imovel_id)
            ->where('vencimento', '<', $mensalidade->pago_em->toDateString())
            ->get();

        $dividasCorrigidas = $mensalidadesAnteriores->sum(function (Mensalidade $m): float {
            $saldo = (float) $m->valor + (float) $m->acrescimo
                - (float) $m->desconto - (float) $m->valor_pago;

            if ($saldo <= 0) {
                return 0.0;
            }

            return $this->correcaoService->corrigirMensalidade(
                $saldo,
                $m->vencimento,
                $m->pago_em ?? now(),
            );
        });

        if ($dividasCorrigidas > 0) {
            $valorFormatado = DinheiroBr::formatar((float) $dividasCorrigidas);
            $recibo .= " Identificamos {$complementoTextoDivida} o valor de R$ {$valorFormatado} em dívidas atrasadas (valores corrigidos pelo IPCA).";
        }

        $cidade = ParametrosCondominio::get('cidade_recibo', 'Rio Branco');

        $pdfName = 'recibo_'.
            $mensalidade->imovel->nome.'_'.
            $mensalidade->ano.'_'.
            str_pad((string) $mensalidade->mes, 2, '0', STR_PAD_LEFT).'.pdf';

        $pdf = app('dompdf.wrapper')->loadView('pdf.recibo-mensalidade', [
            'title' => ParametrosCondominio::nomeCondominio(),
            'subTitle' => ParametrosCondominio::subtituloRecibo(),
            'recibo' => $recibo,
            'data' => $cidade.', '.TextoPtBr::dataExtenso($mensalidade->pago_em),
        ]);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream($pdfName);
    }

    /**
     * pdf.pagamentos.recibo — paridade com PagamentoController::recibo() do legado.
     * DEV-09/DA-09: emissão bloqueada para pagamento estornado (PagamentoPolicy).
     */
    public function reciboPagamento(Pagamento $pagamento): Response
    {
        $this->authorize('emitirRecibo', $pagamento);

        $pagamento->load(['proprietario', 'imovel', 'mensalidades']);

        $pdf = app('dompdf.wrapper')
            ->loadView('pdf.recibo-pagamento', ['pagamento' => $pagamento])
            ->setPaper('a4', 'portrait');

        return $pdf->stream('recibo-pagamento-'.$pagamento->id.'.pdf');
    }

    /**
     * pdf.dividas.por-imovel — paridade com DividaController::print() do legado.
     */
    public function dividasPorImovel(Imovel $imovel): Response
    {
        $mensalidades = Mensalidade::query()
            ->where('imovel_id', $imovel->id)
            ->emAberto()
            ->orderBy('vencimento')
            ->get();

        // 1) Resumo mensal Jan–Dez por ano ('-' quando não há mensalidade)
        $resumoMensal = $mensalidades
            ->groupBy(fn (Mensalidade $m) => $m->vencimento->year)
            ->map(function ($grupoAno) {
                return collect(range(1, 12))->map(function (int $mes) use ($grupoAno): array {
                    $mensalidade = $grupoAno->first(fn (Mensalidade $m) => $m->vencimento->month === $mes);

                    if (! $mensalidade) {
                        return [
                            'valor_num' => 0,
                            'valor_fmt' => '-',
                            'class' => '',
                        ];
                    }

                    $valorCorrigido = $this->correcaoService->corrigirMensalidade(
                        (float) $mensalidade->valor_liquido,
                        $mensalidade->vencimento,
                    );

                    return [
                        'valor_num' => $valorCorrigido,
                        'valor_fmt' => DinheiroBr::formatar($valorCorrigido),
                        'class' => 'text-danger',
                    ];
                });
            });

        // 2) Memória de cálculo detalhada
        $memoriaCalculo = $mensalidades
            ->groupBy(fn (Mensalidade $m) => $m->vencimento->year)
            ->map(function ($grupoAno) {
                return $grupoAno->map(function (Mensalidade $m): array {
                    return $this->correcaoService->memoriaCalculo(
                        (float) $m->valor_liquido,
                        $m->vencimento,
                    ) + [
                        'competencia' => $m->vencimento->format('m/Y'),
                    ];
                });
            });

        $pdf = app('dompdf.wrapper')->loadView('pdf.dividas-imovel', [
            'title' => 'Dívidas do Imóvel',
            'subTitle' => $imovel->nome,
            'mensalidades' => $resumoMensal,
            'memoriaCalculo' => $memoriaCalculo,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('dividas_imovel_'.$imovel->id.'.pdf');
    }

    /**
     * pdf.dividas.consolidado — paridade com DividaController::print_all() do legado.
     */
    public function dividasConsolidado(): Response
    {
        [$dividas, $anos] = $this->resumirDividas(
            Mensalidade::with('imovel')->emAberto()->orderBy('imovel_id')->get(),
        );

        $pdf = app('dompdf.wrapper')->loadView('pdf.dividas-consolidado', [
            'title' => 'Dívidas Atualizadas',
            'subTitle' => 'Valores corrigidos pelo IPCA',
            'dividas' => $dividas,
            'anos' => $anos,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('dividas_atualizadas.pdf');
    }

    /**
     * pdf.despesas.relatorio — paridade com DespesaController::printPrint() do legado.
     * DT-18 corrigido: nome de arquivo sem barras (datas em Y-m-d).
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

        $despesas = Despesa::with('tipo')
            ->where('data', '>=', $dataInicial)
            ->where('data', '<=', $dataFinal)
            ->orderBy('data', 'DESC')
            ->get();

        $bloco = ParametrosCondominio::get('identificacao_bloco', 'Bloco R-04');

        $pdf = app('dompdf.wrapper')->loadView('pdf.despesas-periodo', [
            'title' => ParametrosCondominio::nomeCondominio(),
            'subTitle' => "Despesas - {$bloco} - De: ".date('d/m/Y', strtotime($dataInicial)).' a '.date('d/m/Y', strtotime($dataFinal)),
            'despesas' => $despesas,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('despesas_'.date('Y-m-d', strtotime($dataInicial)).'_'.date('Y-m-d', strtotime($dataFinal)).'.pdf');
    }

    /**
     * pdf.resumo.historico — paridade com PageController::resumoPrint() do legado,
     * com apuração da reserva via cobranças extras (DEV-12) no lugar dos
     * hardcodes de poupança/juros.
     */
    public function resumoHistorico(): Response
    {
        $imoveis = Imovel::orderBy('nome')->pluck('nome', 'nome');
        $mensalidades = Mensalidade::with('imovel')->orderBy('pago_em')->get();
        $despesas = Despesa::orderBy('data')->get();
        $receitas = Receita::orderBy('data')->get();

        [$reservaMensalidades, $reservaReceitas] = $this->apurarReservaCobrancasExtras();

        $resumo = [];
        $totalImovel = [];

        foreach ($mensalidades as $mensalidade) {
            $ano = $mensalidade->pago_em?->format('Y') ?? $mensalidade->vencimento?->format('Y');

            if ($mensalidade->contabilizado) {
                $resumo[$ano][$mensalidade->imovel->nome] =
                    ($resumo[$ano][$mensalidade->imovel->nome] ?? 0) + (float) $mensalidade->valor_pago;

                $totalImovel[$mensalidade->imovel->nome] =
                    ($totalImovel[$mensalidade->imovel->nome] ?? 0) + (float) $mensalidade->valor_pago;
            }
        }

        foreach ($despesas as $despesa) {
            $resumo[(string) $despesa->ano]['despesas'] =
                ($resumo[(string) $despesa->ano]['despesas'] ?? 0) + (float) $despesa->valor;
        }

        foreach ($receitas as $receita) {
            $ano = $receita->data->format('Y');
            $resumo[$ano]['receita'] = ($resumo[$ano]['receita'] ?? 0) + (float) $receita->valor;
        }

        ksort($resumo);

        $bloco = ParametrosCondominio::get('identificacao_bloco', 'Bloco R-04');
        $url = ParametrosCondominio::get('url_sistema', 'http://r4.condominio.space/');

        $pdf = app('dompdf.wrapper')->loadView('pdf.resumo-historico', [
            'title' => ParametrosCondominio::nomeCondominio(),
            'subTitle' => "Resumo das receitas e despesas - {$bloco} - {$url}",
            'resumo' => $resumo,
            'imoveis' => $imoveis,
            'total_imovel' => $totalImovel,
            'poupanca' => $reservaMensalidades,       // DEV-12: cobranças extras via mensalidades
            'juros_poupanca' => $reservaReceitas,     // DEV-12: cobranças extras via receitas
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('resumo_bloco_r4.pdf');
    }

    /**
     * pdf.resumo.intervalo — paridade com PageController::resumo_intervalo_download()
     * do legado, com o saldo anterior corrigido (EX-04: sem o orWhereNull de pago_em)
     * e reserva apurada via cobranças extras (DEV-12).
     */
    public function resumoIntervalo(Request $request): Response
    {
        $de = $request->query('de') ?: date('Y-m-01');
        $ate = $request->query('ate') ?: date('Y-m-t');

        // Saldo anterior — EX-04 corrigido: só mensalidades efetivamente pagas antes de $de
        $valorMensalidades = Mensalidade::query()
            ->where('contabilizado', true)
            ->where('pago_em', '<', $de)
            ->sum('valor_pago');
        $valorDespesas = Despesa::where('data', '<', $de)->sum('valor');
        $valorReceitas = Receita::where('data', '<', $de)->sum('valor');

        $saldo = (float) $valorMensalidades - (float) $valorDespesas + (float) $valorReceitas;

        $mensalidades = Mensalidade::with('imovel.proprietario')
            ->where('contabilizado', true)
            ->where('pago_em', '>=', $de)
            ->where('pago_em', '<=', $ate)
            ->orderBy('pago_em')
            ->get();

        $despesas = Despesa::whereBetween('data', [$de, $ate])->orderBy('data')->get();
        $receitas = Receita::whereBetween('data', [$de, $ate])->orderBy('data')->get();

        [$reservaMensalidades, $reservaReceitas] = $this->apurarReservaCobrancasExtras();

        $pdf = app('dompdf.wrapper')->loadView('pdf.resumo-intervalo', [
            'saldo' => $saldo,
            'mensalidades' => $mensalidades,
            'despesas' => $despesas,
            'receitas' => $receitas,
            'poupanca' => $reservaMensalidades,       // DEV-12
            'juros_poupanca' => $reservaReceitas,     // DEV-12
            'de' => $de,
            'ate' => $ate,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('resumo_intervalo'.$de.'_a_'.$ate.'_bloco_r4.pdf');
    }

    /**
     * Consolida dívidas corrigidas por imóvel × ano (paridade DividaController::resumirDividas()).
     *
     * @return array{0: array<string, array<int, float>>, 1: array<int, int>}
     */
    private function resumirDividas($mensalidades): array
    {
        $dividas = [];
        $anos = [];

        foreach ($mensalidades as $m) {
            $ano = $m->vencimento->year;
            $imovel = $m->imovel->nome;

            $valorCorrigido = $this->correcaoService->corrigirMensalidade(
                (float) $m->valor_liquido,
                $m->vencimento,
            );

            $dividas[$imovel][$ano] = ($dividas[$imovel][$ano] ?? 0) + $valorCorrigido;

            $anos[$ano] = $ano;
        }

        ksort($anos);

        return [$dividas, $anos];
    }

    /**
     * DEV-12 — apuração da reserva por cobranças extras, substituindo os
     * hardcodes do legado (valor_pago == 150 * 50 e receitas > 2024-12-31):
     * soma dos pivots cobranca_extra_mensalidade + receitas vinculadas a cobrança extra.
     *
     * @return array{0: float, 1: float}
     */
    private function apurarReservaCobrancasExtras(): array
    {
        $reservaMensalidades = (float) DB::table('cobranca_extra_mensalidade')->sum('valor');
        $reservaReceitas = (float) Receita::whereNotNull('cobranca_extra_id')->sum('valor');

        return [$reservaMensalidades, $reservaReceitas];
    }
}
