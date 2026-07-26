<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\CobrancasExtraordinarias;

use App\Actions\Financeiro\SalvarCobrancaExtraordinaria;
use App\Enums\MetodoRateio;
use App\Models\CobrancaExtraordinaria;
use App\Models\Configuracao;
use App\Models\LancamentoFinanceiro;
use App\Support\DinheiroBr;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Cobranças extraordinárias no modelo novo (substitui CobrancasExtras no
 * cutover): ganha método de rateio. Acesso admin+sindico (gestão do
 * condomínio — mesma policy das configurações). Mostra o total apurado por
 * cobrança (pivot de taxas + lançamentos de receita com origem na cobrança).
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

    public bool $formAtiva = true;

    public string $mensagem = '';

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
        $this->formAtiva = true;
        $this->formAberto = true;
        $this->mensagem = '';
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
        $this->formAtiva = (bool) $cobranca->ativa;
        $this->formAberto = true;
        $this->mensagem = '';
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
        } catch (\InvalidArgumentException) {
            $this->addError('formValor', 'Valor monetário inválido.');

            return;
        }

        $cobranca = $this->cobrancaId !== null
            ? CobrancaExtraordinaria::query()->findOrFail($this->cobrancaId)
            : null;

        $acao->executar([
            'nome' => $this->formNome,
            'valor_total' => $valor,
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

    public function render()
    {
        $cobrancas = CobrancaExtraordinaria::query()
            ->withSum('taxasCondominiais as total_taxas', 'cobranca_extraordinaria_taxa.valor')
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
        ]);
    }
}
