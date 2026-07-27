<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Taxas;

use App\Models\TaxaCondominial;
use App\Models\Unidade;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listagem de taxas condominiais por unidade/ano (modelo novo — substitui
 * a listagem de mensalidades no cutover). O valor pago é derivado da soma
 * de pagamento_taxa; a data do pagamento é a do último pagamento aplicado.
 * O valor devido é discriminado pelos itens da composição (Etapa 5 de
 * docs/migration/05-plano-composicao-taxas.md).
 */
#[Layout('layouts.app')]
#[Title('Taxas')]
class Listagem extends Component
{
    use WithPagination;

    public int $ano;

    #[Url(as: 'unidade')]
    public ?int $unidadeId = null;

    public function mount(?int $ano = null): void
    {
        $this->ano = $ano ?? (int) now()->format('Y');
    }

    public function updatedAno(): void
    {
        $this->resetPage();
    }

    public function updatedUnidadeId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $unidades = Unidade::query()
            ->with(['vinculos' => fn ($q) => $q->whereNull('data_fim')->orderByDesc('responsavel_financeiro')->with('pessoa')])
            ->orderBy('identificacao')
            ->get();

        $unidadeSelecionada = $this->unidadeId !== null
            ? $unidades->firstWhere('id', $this->unidadeId)
            : null;

        $taxas = null;
        $totalValor = '0.00';
        $totalPago = '0.00';

        if ($unidadeSelecionada !== null) {
            $base = TaxaCondominial::query()
                ->where('unidade_id', $unidadeSelecionada->id)
                ->where('competencia_ano', $this->ano);

            $totalValor = (string) (clone $base)->sum('valor_original');
            $totalPago = (string) ((clone $base)
                ->join('pagamento_taxa', 'pagamento_taxa.taxa_condominial_id', '=', 'taxas_condominiais.id')
                ->sum('pagamento_taxa.valor_aplicado') ?: '0');

            $taxas = (clone $base)
                ->withSum('pagamentoTaxas as valor_pago', 'valor_aplicado')
                ->withMax('pagamentos as ultimo_pagamento', 'data_pagamento')
                // Composição da mensalidade (05-plano-composicao-taxas.md):
                // a listagem discrimina os itens que formam o valor devido
                ->with('itens.finalidade')
                ->orderBy('competencia_mes')
                ->paginate(12);
        }

        return view('livewire.financeiro.taxas.listagem', [
            'unidades' => $unidades,
            'unidadeSelecionada' => $unidadeSelecionada,
            'taxas' => $taxas,
            'totalValor' => $totalValor,
            'totalPago' => $totalPago,
        ]);
    }
}
