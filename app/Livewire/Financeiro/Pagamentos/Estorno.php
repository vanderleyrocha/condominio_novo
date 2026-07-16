<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Pagamentos;

use App\Actions\Financeiro\EstornarPagamento;
use App\Models\Pagamento;
use DomainException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Estorno de pagamento (tela pagamentos.estorno).
 * DEV-04: total a estornar deixa de ser JS vanilla e vira propriedade computada.
 * Regras de teto e reabertura de mensalidade vivem na Action EstornarPagamento.
 */
#[Layout('layouts.app')]
class Estorno extends Component
{
    public Pagamento $pagamento;

    /** @var array<int, string> [mensalidade_id => valor a estornar] */
    public array $valores = [];

    public string $erro = '';

    public function mount(Pagamento $pagamento): void
    {
        if ($pagamento->estornado) {
            session()->flash('error', 'Este pagamento já foi estornado.');

            $this->redirectRoute('pagamentos.show', ['pagamento' => $pagamento]);

            return;
        }

        $this->authorize('estornar', $pagamento);

        $pagamento->load(['proprietario', 'imovel', 'mensalidades']);

        $this->pagamento = $pagamento;

        foreach ($pagamento->mensalidades as $mensalidade) {
            $this->valores[$mensalidade->id] = (string) $mensalidade->pivot->valor;
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

    public function confirmar(EstornarPagamento $acao): void
    {
        $this->erro = '';

        $valores = [];

        foreach ($this->valores as $mensalidadeId => $valor) {
            if (! is_numeric($valor) || (float) $valor < 0) {
                $this->addError("valores.{$mensalidadeId}", 'Informe um valor válido (maior ou igual a zero).');

                return;
            }

            $valores[(int) $mensalidadeId] = (float) $valor;
        }

        try {
            $acao->executar($this->pagamento, $valores);
        } catch (DomainException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        session()->flash('status', 'Estorno realizado com sucesso.');

        $this->redirectRoute('pagamentos.show', ['pagamento' => $this->pagamento]);
    }

    public function render()
    {
        return view('livewire.financeiro.pagamentos.estorno');
    }
}
