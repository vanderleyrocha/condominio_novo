<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\PagamentosNovo;

use App\Actions\Financeiro\EstornarPagamentoNovo;
use App\Models\PagamentoNovo;
use DomainException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Estorno de pagamento no modelo novo. Regras de teto e recálculo de status
 * vivem na Action EstornarPagamentoNovo.
 */
#[Layout('layouts.app')]
class Estorno extends Component
{
    public PagamentoNovo $pagamento;

    /** @var array<int, string> [taxa_id => valor a estornar] */
    public array $valores = [];

    public string $erro = '';

    public function mount(PagamentoNovo $pagamento): void
    {
        if ($pagamento->estornos()->exists()) {
            session()->flash('error', 'Este pagamento já foi estornado.');

            $this->redirectRoute('pagamentos-novo.show', ['pagamento' => $pagamento]);

            return;
        }

        $this->authorize('estornar', $pagamento);

        $pagamento->load(['pessoa', 'unidade', 'taxasCondominiais']);

        $this->pagamento = $pagamento;

        foreach ($pagamento->taxasCondominiais as $taxa) {
            $this->valores[$taxa->id] = (string) $taxa->pivot->valor_aplicado;
        }
    }

    #[Computed]
    public function totalAEstornar(): float
    {
        return array_sum(array_map(
            static fn ($valor): float => is_numeric($valor) ? (float) $valor : 0.0,
            $this->valores,
        ));
    }

    public function confirmar(EstornarPagamentoNovo $acao): void
    {
        $this->erro = '';

        $valores = [];

        foreach ($this->valores as $taxaId => $valor) {
            if (! is_numeric($valor) || (float) $valor < 0) {
                $this->addError("valores.{$taxaId}", 'Informe um valor válido (maior ou igual a zero).');

                return;
            }

            $valores[(int) $taxaId] = (float) $valor;
        }

        try {
            $acao->executar($this->pagamento, $valores);
        } catch (DomainException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        session()->flash('status', 'Estorno realizado com sucesso.');

        $this->redirectRoute('pagamentos-novo.show', ['pagamento' => $this->pagamento]);
    }

    public function render()
    {
        return view('livewire.financeiro.pagamentos-novo.estorno');
    }
}
