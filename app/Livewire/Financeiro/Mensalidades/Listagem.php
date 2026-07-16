<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Mensalidades;

use App\Models\Imovel;
use App\Models\Mensalidade;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listagem de mensalidades por imóvel/ano (tela mensalidades.listagem).
 * Filtros reativos (DEV-04): sem full-reload ao trocar ano/imóvel.
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    use WithPagination;

    public int $ano;

    #[Url(as: 'imovel')]
    public ?int $imovelId = null;

    public function mount(?int $ano = null): void
    {
        $this->ano = $ano ?? (int) now()->format('Y');
    }

    public function updatedAno(): void
    {
        $this->resetPage();
    }

    public function updatedImovelId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $imoveis = Imovel::query()->with('proprietario')->orderBy('nome')->get();

        $imovelSelecionado = $this->imovelId !== null
            ? $imoveis->firstWhere('id', $this->imovelId)
            : null;

        $mensalidades = null;
        $totalValor = '0.00';
        $totalValorPago = '0.00';

        if ($imovelSelecionado !== null) {
            $base = Mensalidade::query()
                ->where('imovel_id', $imovelSelecionado->id)
                ->where('ano', $this->ano);

            $totalValor = (string) (clone $base)->sum('valor');
            $totalValorPago = (string) (clone $base)->sum('valor_pago');

            $mensalidades = (clone $base)->orderBy('mes')->paginate(12);
        }

        return view('livewire.financeiro.mensalidades.listagem', [
            'imoveis' => $imoveis,
            'imovelSelecionado' => $imovelSelecionado,
            'mensalidades' => $mensalidades,
            'totalValor' => $totalValor,
            'totalValorPago' => $totalValorPago,
        ]);
    }
}
