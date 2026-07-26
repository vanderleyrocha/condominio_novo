<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Taxas;

use App\Enums\StatusTaxa;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Relatórios de taxas (modelo novo — substitui os relatórios de mensalidades
 * no cutover): listagem filtrável por unidade/ano/mês/status. No modelo novo
 * o status é coluna derivada — o filtro dispensa a reconstrução por SQL cru
 * do legado; "vencida" é aberto/parcial com vencimento passado.
 */
#[Layout('layouts.app')]
#[Title('Relatórios')]
class Relatorios extends Component
{
    use WithPagination;

    public ?int $unidadeId = null;

    public ?int $ano = null;

    public ?int $mes = null;

    public string $status = '';

    public function mount(): void
    {
        $this->ano = (int) now()->format('Y');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['unidadeId', 'ano', 'mes', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $query = TaxaCondominial::query()
            ->with(['unidade.vinculos' => fn ($q) => $q->whereNull('data_fim')->orderByDesc('responsavel_financeiro')->with('pessoa')])
            ->withSum('pagamentoTaxas as valor_pago', 'valor_aplicado')
            ->when($this->unidadeId, fn ($q) => $q->where('unidade_id', $this->unidadeId))
            ->when($this->ano, fn ($q) => $q->where('competencia_ano', $this->ano))
            ->when($this->mes, fn ($q) => $q->where('competencia_mes', $this->mes));

        $query = match ($this->status) {
            StatusTaxa::Pago->value => $query->where('status', StatusTaxa::Pago->value),
            StatusTaxa::PagoParcial->value => $query->where('status', StatusTaxa::PagoParcial->value),
            'vencida' => $query
                ->where('status', '!=', StatusTaxa::Pago->value)
                ->where('vencimento', '<', now()->toDateString()),
            StatusTaxa::Aberto->value => $query
                ->where('status', StatusTaxa::Aberto->value)
                ->where('vencimento', '>=', now()->toDateString()),
            default => $query,
        };

        $totais = (clone $query)
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(valor_original), 0) as total_valor')
            ->first();

        $taxas = $query
            ->orderBy('competencia_ano')
            ->orderBy('competencia_mes')
            ->orderBy('unidade_id')
            ->paginate(20);

        // Total pago do filtro: soma dos itens filtrados (evita subquery frágil)
        $totalPago = (clone $query)
            ->join('pagamento_taxa', 'pagamento_taxa.taxa_condominial_id', '=', 'taxas_condominiais.id')
            ->sum('pagamento_taxa.valor_aplicado');

        return view('livewire.financeiro.taxas.relatorios', [
            'unidades' => Unidade::query()->orderBy('identificacao')->get(),
            'taxas' => $taxas,
            'quantidade' => (int) ($totais->quantidade ?? 0),
            'totalValor' => (string) ($totais->total_valor ?? '0'),
            'totalPago' => (string) ($totalPago ?: '0'),
        ]);
    }
}
