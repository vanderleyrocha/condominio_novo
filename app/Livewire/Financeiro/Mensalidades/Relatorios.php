<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Mensalidades;

use App\Enums\StatusMensalidade;
use App\Models\Imovel;
use App\Models\Mensalidade;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Relatórios de mensalidades (telas mensalidades.relatorio.*): listagem filtrável
 * por imóvel/ano/mês/status. Os PDFs de relatório geral virão em rotas dedicadas;
 * aqui apenas a consulta com link de recibo por linha paga.
 */
#[Layout('layouts.app')]
class Relatorios extends Component
{
    use WithPagination;

    public ?int $imovelId = null;

    public ?int $ano = null;

    public ?int $mes = null;

    public string $status = '';

    public function mount(): void
    {
        $this->ano = (int) now()->format('Y');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['imovelId', 'ano', 'mes', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $query = Mensalidade::query()
            ->with('imovel.proprietario')
            ->when($this->imovelId, fn ($q) => $q->where('imovel_id', $this->imovelId))
            ->when($this->ano, fn ($q) => $q->where('ano', $this->ano))
            ->when($this->mes, fn ($q) => $q->where('mes', $this->mes));

        // Filtro de status com a mesma semântica de Mensalidade::status()
        $query = match ($this->status) {
            StatusMensalidade::Paga->value => $query
                ->whereRaw('COALESCE(valor_pago, 0) > 0')
                ->whereRaw('COALESCE(valor_pago, 0) >= (COALESCE(valor, 0) - COALESCE(desconto, 0))'),
            StatusMensalidade::PagaParcial->value => $query
                ->whereRaw('COALESCE(valor_pago, 0) > 0')
                ->whereRaw('COALESCE(valor_pago, 0) < (COALESCE(valor, 0) - COALESCE(desconto, 0))'),
            StatusMensalidade::Vencida->value => $query
                ->whereRaw('COALESCE(valor_pago, 0) = 0')
                ->where('vencimento', '<', now()->toDateString()),
            StatusMensalidade::EmAberto->value => $query
                ->whereRaw('COALESCE(valor_pago, 0) = 0')
                ->where('vencimento', '>=', now()->toDateString()),
            default => $query,
        };

        $totais = (clone $query)
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(valor), 0) as total_valor, COALESCE(SUM(valor_pago), 0) as total_pago')
            ->first();

        $mensalidades = $query
            ->orderBy('ano')
            ->orderBy('mes')
            ->orderBy('imovel_id')
            ->paginate(20);

        return view('livewire.financeiro.mensalidades.relatorios', [
            'imoveis' => Imovel::query()->with('proprietario')->orderBy('nome')->get(),
            'mensalidades' => $mensalidades,
            'totais' => $totais,
        ]);
    }
}
