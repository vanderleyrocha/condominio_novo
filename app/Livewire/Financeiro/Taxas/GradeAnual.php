<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Taxas;

use App\Actions\Financeiro\PagarViaGrade;
use App\Models\Unidade;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Grade anual unidade × 12 meses (modelo novo). A célula mostra o TOTAL PAGO
 * derivado de pagamento_taxa; alterações geram pagamentos reais via
 * PagarViaGrade (delta positivo = pagamento; negativo = ajuste).
 */
#[Layout('layouts.app')]
#[Title('Grade anual')]
class GradeAnual extends Component
{
    public int $ano;

    /** @var array<int, string> [taxa_id => total pago] */
    public array $valores = [];

    public string $erro = '';

    public function mount(int $ano): void
    {
        $this->ano = $ano;

        foreach ($this->unidadesComTaxas() as $unidade) {
            foreach ($unidade->taxasCondominiais as $taxa) {
                $this->valores[$taxa->id] = (string) ($taxa->valor_pago ?? '0.00');
            }
        }
    }

    public function gravar(PagarViaGrade $acao): void
    {
        $this->erro = '';

        try {
            $gravadas = $acao->executar($this->valores, auth()->user());
        } catch (AuthorizationException|DomainException $e) {
            $this->erro = $e->getMessage() !== ''
                ? $e->getMessage()
                : 'Atualização não autorizada.';

            return;
        }

        session()->flash('status', "Grade gravada com sucesso: {$gravadas} taxa(s) atualizada(s).");

        $this->redirectRoute('taxas.index', ['ano' => $this->ano]);
    }

    /**
     * @return Collection<int, Unidade>
     */
    private function unidadesComTaxas(): Collection
    {
        return Unidade::query()
            ->with(['taxasCondominiais' => fn ($query) => $query
                ->where('competencia_ano', $this->ano)
                ->withSum('pagamentoTaxas as valor_pago', 'valor_aplicado')
                ->orderBy('competencia_mes')])
            ->orderBy('identificacao')
            ->get();
    }

    public function render()
    {
        return view('livewire.financeiro.taxas.grade-anual', [
            'unidades' => $this->unidadesComTaxas(),
        ]);
    }
}
