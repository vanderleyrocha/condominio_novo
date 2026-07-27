<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\CobrancasExtraordinarias;

use App\Actions\Financeiro\AplicarCobrancaEmTaxas;
use App\Actions\Financeiro\SalvarCobrancaExtraordinaria;
use App\Enums\MetodoRateio;
use App\Models\CobrancaExtraordinaria;
use App\Models\Configuracao;
use App\Models\Finalidade;
use App\Models\LancamentoFinanceiro;
use App\Support\DinheiroBr;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Cobranças extraordinárias no modelo novo (substitui CobrancasExtras no
 * cutover): ganha método de rateio. Acesso admin+sindico (gestão do
 * condomínio — mesma policy das configurações).
 *
 * Etapa 5 de docs/migration/05-plano-composicao-taxas.md: a cobrança é a
 * CAMPANHA (D-04) — ganha finalidade e valor_por_unidade, e passa a ser
 * aplicada nas taxas como ITEM da composição (AplicarCobrancaEmTaxas), em vez
 * de manipular o pivô cobranca_extraordinaria_taxa, descontinuado na Etapa 6.
 * O apurado vem dos itens gerados + lançamentos de receita com origem nela.
 */
#[Layout('layouts.app')]
#[Title('Cobranças extraordinárias')]
class Gestao extends Component
{
    public bool $formAberto = false;

    public ?int $cobrancaId = null;

    #[Validate('required|string|max:255', as: 'Nome')]
    public string $formNome = '';

    #[Validate('required|string', as: 'Valor')]
    public string $formValor = '0,00';

    #[Validate('required|string', as: 'Método de rateio')]
    public string $formMetodoRateio = 'manual';

    #[Validate('required|date', as: 'Vigência início')]
    public string $formVigenciaInicio = '';

    #[Validate('nullable|date|after_or_equal:formVigenciaInicio', as: 'Vigência fim')]
    public string $formVigenciaFim = '';

    #[Validate('nullable|string', as: 'Valor por unidade')]
    public string $formValorPorUnidade = '';

    #[Validate('nullable|integer|exists:finalidades,id', as: 'Finalidade')]
    public ?int $formFinalidadeId = null;

    public bool $formAtiva = true;

    public string $mensagem = '';

    public string $erro = '';

    // --- Aplicação da campanha nas taxas (intervalo de competências) ---

    public ?int $aplicarCobrancaId = null;

    #[Validate('nullable|integer|min:2000|max:2100', as: 'Ano inicial')]
    public ?int $aplicarAnoInicio = null;

    #[Validate('nullable|integer|min:1|max:12', as: 'Mês inicial')]
    public ?int $aplicarMesInicio = null;

    #[Validate('nullable|integer|min:2000|max:2100', as: 'Ano final')]
    public ?int $aplicarAnoFim = null;

    #[Validate('nullable|integer|min:1|max:12', as: 'Mês final')]
    public ?int $aplicarMesFim = null;

    public function mount(): void
    {
        $this->authorize('gerenciar', Configuracao::class);
    }

    public function novaCobranca(): void
    {
        $this->resetErrorBag();
        $this->cobrancaId = null;
        $this->formNome = '';
        $this->formValor = '0,00';
        $this->formMetodoRateio = 'manual';
        $this->formVigenciaInicio = date('Y-m-d');
        $this->formVigenciaFim = '';
        $this->formValorPorUnidade = '';
        $this->formFinalidadeId = null;
        $this->formAtiva = true;
        $this->formAberto = true;
        $this->mensagem = '';
        $this->erro = '';
    }

    public function editar(int $id): void
    {
        $cobranca = CobrancaExtraordinaria::query()->findOrFail($id);

        $this->resetErrorBag();
        $this->cobrancaId = $cobranca->id;
        $this->formNome = $cobranca->nome;
        $this->formValor = DinheiroBr::formatar($cobranca->valor_total);
        $this->formMetodoRateio = $cobranca->metodo_rateio->value;
        $this->formVigenciaInicio = $cobranca->vigencia_inicio->toDateString();
        $this->formVigenciaFim = $cobranca->vigencia_fim?->toDateString() ?? '';
        $this->formValorPorUnidade = $cobranca->valor_por_unidade === null
            ? ''
            : DinheiroBr::formatar($cobranca->valor_por_unidade);
        $this->formFinalidadeId = $cobranca->finalidade_id;
        $this->formAtiva = (bool) $cobranca->ativa;
        $this->formAberto = true;
        $this->mensagem = '';
        $this->erro = '';
    }

    public function cancelar(): void
    {
        $this->formAberto = false;
        $this->resetErrorBag();
    }

    public function salvar(SalvarCobrancaExtraordinaria $acao): void
    {
        $this->authorize('gerenciar', Configuracao::class);
        $this->validate();

        try {
            $valor = DinheiroBr::paraDecimal($this->formValor);
            $valorPorUnidade = $this->formValorPorUnidade === ''
                ? null
                : DinheiroBr::paraDecimal($this->formValorPorUnidade);
        } catch (\InvalidArgumentException) {
            $this->addError('formValor', 'Valor monetário inválido.');

            return;
        }

        $cobranca = $this->cobrancaId !== null
            ? CobrancaExtraordinaria::query()->findOrFail($this->cobrancaId)
            : null;

        $acao->executar([
            'nome' => $this->formNome,
            'finalidade_id' => $this->formFinalidadeId,
            'valor_total' => $valor,
            'valor_por_unidade' => $valorPorUnidade,
            'metodo_rateio' => MetodoRateio::from($this->formMetodoRateio),
            'vigencia_inicio' => $this->formVigenciaInicio,
            'vigencia_fim' => $this->formVigenciaFim !== '' ? $this->formVigenciaFim : null,
            'ativa' => $this->formAtiva,
        ], $cobranca);

        $this->formAberto = false;
        $this->mensagem = $cobranca === null
            ? 'Cobrança extraordinária cadastrada com sucesso.'
            : 'Cobrança extraordinária atualizada com sucesso.';
    }

    /**
     * Abre o painel de aplicação da campanha num intervalo de competências,
     * pré-preenchido com a vigência da própria cobrança.
     */
    public function abrirAplicacao(int $id): void
    {
        $cobranca = CobrancaExtraordinaria::query()->findOrFail($id);

        $this->resetErrorBag();
        $this->mensagem = '';
        $this->erro = '';
        $this->aplicarCobrancaId = $cobranca->id;
        $this->aplicarAnoInicio = (int) $cobranca->vigencia_inicio->format('Y');
        $this->aplicarMesInicio = (int) $cobranca->vigencia_inicio->format('n');
        $this->aplicarAnoFim = (int) ($cobranca->vigencia_fim?->format('Y') ?? now()->format('Y'));
        $this->aplicarMesFim = (int) ($cobranca->vigencia_fim?->format('n') ?? 12);
    }

    public function fecharAplicacao(): void
    {
        $this->aplicarCobrancaId = null;
        $this->resetErrorBag();
    }

    public function aplicarNasTaxas(AplicarCobrancaEmTaxas $acao): void
    {
        $this->executarAplicacao(
            fn (CobrancaExtraordinaria $c): array => $acao->aplicar(
                $c, $this->aplicarAnoInicio, $this->aplicarMesInicio, $this->aplicarAnoFim, $this->aplicarMesFim,
            ),
            'aplicadas',
            'aplicada em',
        );
    }

    public function retirarDasTaxas(AplicarCobrancaEmTaxas $acao): void
    {
        $this->executarAplicacao(
            fn (CobrancaExtraordinaria $c): array => $acao->retirar(
                $c, $this->aplicarAnoInicio, $this->aplicarMesInicio, $this->aplicarAnoFim, $this->aplicarMesFim,
            ),
            'retiradas',
            'retirada de',
        );
    }

    /**
     * @param  callable(CobrancaExtraordinaria): array{aplicadas?: int, retiradas?: int, ignoradas: list<string>}  $operacao
     */
    private function executarAplicacao(callable $operacao, string $chave, string $rotulo): void
    {
        $this->authorize('gerenciar', Configuracao::class);
        $this->validate();
        $this->erro = '';

        $cobranca = CobrancaExtraordinaria::query()->findOrFail($this->aplicarCobrancaId);

        try {
            $resultado = $operacao($cobranca);
        } catch (DomainException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->mensagem = "Cobrança {$rotulo} {$resultado[$chave]} competência(s).";

        if ($resultado['ignoradas'] !== []) {
            $this->mensagem .= ' Ignoradas por já terem pagamento aplicado: '
                .implode('; ', array_slice($resultado['ignoradas'], 0, 10))
                .(count($resultado['ignoradas']) > 10 ? ' …' : '');
        }

        $this->aplicarCobrancaId = null;
    }

    public function render()
    {
        $cobrancas = CobrancaExtraordinaria::query()
            // Apurado agora vem dos ITENS gerados pela campanha (D-04), não do pivô
            ->withSum('itensTaxa as total_taxas', 'valor')
            ->with('finalidade')
            ->orderBy('nome')
            ->get();

        // Receitas com origem na cobrança (lançamentos polimórficos)
        $receitasPorCobranca = LancamentoFinanceiro::query()
            ->where('origem_type', CobrancaExtraordinaria::class)
            ->selectRaw('origem_id, COALESCE(SUM(valor), 0) as total')
            ->groupBy('origem_id')
            ->pluck('total', 'origem_id');

        return view('livewire.financeiro.cobrancas-extraordinarias.gestao', [
            'cobrancas' => $cobrancas,
            'receitasPorCobranca' => $receitasPorCobranca,
            'metodos' => MetodoRateio::cases(),
            'finalidades' => Finalidade::query()->ativas()->orderBy('nome')->pluck('nome', 'id'),
        ]);
    }
}
