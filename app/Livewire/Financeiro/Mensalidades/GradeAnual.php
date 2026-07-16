<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Mensalidades;

use App\Actions\Financeiro\AtualizarGradeAnual;
use App\Models\Imovel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Grade anual imóvel × 12 meses (tela mensalidades.grade-anual).
 * DEV-07: dirty-tracking fica na Action AtualizarGradeAnual (persistência seletiva:
 * só grava célula alterada; pago_em = hoje se valor > 0, senão null).
 */
#[Layout('layouts.app')]
class GradeAnual extends Component
{
    public int $ano;

    /** @var array<int, string> [mensalidade_id => valor_pago] */
    public array $valores = [];

    public string $erro = '';

    public function mount(int $ano): void
    {
        $this->ano = $ano;

        foreach ($this->imoveisComMensalidades() as $imovel) {
            foreach ($imovel->mensalidades as $mensalidade) {
                $this->valores[$mensalidade->id] = (string) $mensalidade->valor_pago;
            }
        }
    }

    public function gravar(AtualizarGradeAnual $acao): void
    {
        $this->erro = '';

        try {
            $gravadas = $acao->executar($this->valores, auth()->user());
        } catch (AuthorizationException $e) {
            $this->erro = $e->getMessage() !== ''
                ? $e->getMessage()
                : 'Atualização não autorizada ou data anterior a autorização do seu usuário';

            return;
        }

        session()->flash('status', "Grade gravada com sucesso: {$gravadas} mensalidade(s) atualizada(s).");

        $this->redirectRoute('mensalidades.index', ['ano' => $this->ano]);
    }

    /**
     * Eager loading — um único par de queries (imóveis + mensalidades do ano), sem N+1.
     *
     * @return Collection<int, Imovel>
     */
    private function imoveisComMensalidades(): Collection
    {
        return Imovel::query()
            ->with(['mensalidades' => fn ($query) => $query->where('ano', $this->ano)->orderBy('mes')])
            ->orderBy('nome')
            ->get();
    }

    public function render()
    {
        return view('livewire.financeiro.mensalidades.grade-anual', [
            'imoveis' => $this->imoveisComMensalidades(),
        ]);
    }
}
