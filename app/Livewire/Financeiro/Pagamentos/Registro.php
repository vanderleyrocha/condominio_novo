<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Pagamentos;

use App\Actions\Financeiro\RegistrarPagamento;
use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Models\Proprietario;
use App\Support\DinheiroBr;
use App\Support\ParametrosCondominio;
use DomainException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Registro de pagamento (tela pagamentos.registro).
 * DEV-08: sem endpoint JSON — seleção de proprietário, filtro de anos e simulação
 * FIFO são reativos no componente; a persistência fica na Action RegistrarPagamento.
 */
#[Layout('layouts.app')]
class Registro extends Component
{
    #[Validate('required|date')]
    public string $data = '';

    #[Validate('required|string|max:255')]
    public string $descricao = '';

    #[Validate('required|string')]
    public string $valor = '';

    #[Validate('required|integer|exists:proprietarios,id')]
    public ?int $proprietarioId = null;

    /** @var list<int|string> anos marcados no filtro */
    public array $anos = [];

    public bool $todosAnos = false;

    /** @var list<int|string> ids das mensalidades selecionadas */
    public array $selecionadas = [];

    public string $erro = '';

    public function mount(): void
    {
        $this->authorize('create', Pagamento::class);

        $this->data = now()->toDateString();
    }

    /** @return list<int> */
    public function anosDisponiveis(): array
    {
        return range(ParametrosCondominio::anoInicialFiltroPagamentos(), (int) now()->format('Y'));
    }

    public function updatedProprietarioId(): void
    {
        $this->selecionadas = [];
    }

    public function updatedTodosAnos(bool $marcado): void
    {
        $this->anos = $marcado ? $this->anosDisponiveis() : [];
        $this->selecionadas = [];
    }

    public function updatedAnos(): void
    {
        $this->todosAnos = count($this->anos) === count($this->anosDisponiveis());
        $this->selecionadas = [];
    }

    #[Computed]
    public function proprietarios(): Collection
    {
        return Proprietario::query()->orderBy('nome')->get();
    }

    /**
     * Mensalidades em aberto do primeiro imóvel do proprietário:
     * (valor + acrescimo - desconto) > IFNULL(valor_pago, 0), ordem ano/mês.
     */
    #[Computed]
    public function mensalidadesEmAberto(): Collection
    {
        if ($this->proprietarioId === null || $this->anos === []) {
            return collect();
        }

        $proprietario = Proprietario::query()->find($this->proprietarioId);
        $imovel = $proprietario?->imoveis()->first();

        if ($imovel === null) {
            return collect();
        }

        return Mensalidade::query()
            ->where('imovel_id', $imovel->id)
            ->whereRaw('(COALESCE(valor, 0) + COALESCE(acrescimo, 0) - COALESCE(desconto, 0)) > COALESCE(valor_pago, 0)')
            ->whereIn('ano', array_map('intval', $this->anos))
            ->orderBy('ano')
            ->orderBy('mes')
            ->get();
    }

    /**
     * Simulação da alocação FIFO (mesma regra da Action): min(devido, saldo)
     * por mensalidade selecionada, em ordem cronológica.
     *
     * @return array{linhas: array<int, float>, saldo: float}
     */
    #[Computed]
    public function alocacao(): array
    {
        try {
            $saldo = (float) DinheiroBr::paraDecimal($this->valor);
        } catch (InvalidArgumentException) {
            $saldo = 0.0;
        }

        $selecionadas = array_map('intval', $this->selecionadas);
        $linhas = [];

        foreach ($this->mensalidadesEmAberto() as $mensalidade) {
            if (! in_array($mensalidade->id, $selecionadas, true)) {
                continue;
            }

            $devido = (float) $mensalidade->valorDevido();
            $aplicado = max(min($devido, $saldo), 0.0);
            $linhas[$mensalidade->id] = $aplicado;
            $saldo -= $aplicado;
        }

        return ['linhas' => $linhas, 'saldo' => $saldo];
    }

    public function salvar(RegistrarPagamento $acao): void
    {
        $this->erro = '';
        $this->validate();

        try {
            $valor = (float) DinheiroBr::paraDecimal($this->valor);
        } catch (InvalidArgumentException) {
            $this->addError('valor', 'Informe um valor monetário válido.');

            return;
        }

        $proprietario = Proprietario::query()->findOrFail($this->proprietarioId);

        try {
            $pagamento = $acao->executar(
                $proprietario,
                $this->data,
                $this->descricao,
                $valor,
                array_map('intval', $this->selecionadas),
            );
        } catch (DomainException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        session()->flash('status', 'Pagamento registrado com sucesso.');

        $this->redirectRoute('pagamentos.show', ['pagamento' => $pagamento]);
    }

    public function render()
    {
        return view('livewire.financeiro.pagamentos.registro');
    }
}
