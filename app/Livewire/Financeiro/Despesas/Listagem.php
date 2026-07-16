<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Despesas;

use App\Actions\Financeiro\SalvarDespesa;
use App\Models\Despesa;
use App\Models\DespesaTipo;
use App\Support\DinheiroBr;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Despesas — listagem com a busca multi-critério do legado (valor, mês PT-BR,
 * data DD/MM/AAAA ou texto) e criação/edição via Action SalvarDespesa.
 * Sem exclusão (paridade RN-29).
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    private const MESES = [
        1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

    use WithPagination;

    #[Url]
    public string $ano = '';

    #[Url]
    public string $busca = '';

    #[Url]
    public int $tipo = 0;

    public string $dataInicial = '';

    public string $dataFinal = '';

    public bool $formAberto = false;

    public ?int $despesaId = null;

    #[Validate('required|integer|exists:despesa_tipos,id', as: 'Tipo')]
    public ?int $formTipoId = null;

    #[Validate('required|date', as: 'Data')]
    public string $formData = '';

    #[Validate('required|string|max:255', as: 'Descrição')]
    public string $formDescricao = '';

    #[Validate('required|string', as: 'Valor')]
    public string $formValor = '0,00';

    public bool $formContabilizado = false;

    public string $novoTipo = '';

    public string $mensagem = '';

    public string $erro = '';

    public function mount(): void
    {
        if ($this->ano === '') {
            $this->ano = date('Y');
        }

        $this->dataInicial = $this->ano.'-01-01';
        $this->dataFinal = date('Y-m-d');
    }

    public function updated(string $propriedade): void
    {
        if (in_array($propriedade, ['ano', 'busca', 'tipo'], true)) {
            $this->resetPage();
        }
    }

    public function novaDespesa(): void
    {
        $this->resetErrorBag();
        $this->despesaId = null;
        $this->formTipoId = null;
        $this->formData = date('Y-m-d');
        $this->formDescricao = '';
        $this->formValor = '0,00';
        $this->formContabilizado = false;
        $this->formAberto = true;
        $this->mensagem = '';
        $this->erro = '';
    }

    public function editar(int $id): void
    {
        $despesa = Despesa::query()->findOrFail($id);

        $this->resetErrorBag();
        $this->despesaId = $despesa->id;
        $this->formTipoId = $despesa->despesa_tipo_id;
        $this->formData = $despesa->data->toDateString();
        $this->formDescricao = $despesa->descricao;
        $this->formValor = DinheiroBr::formatar($despesa->valor);
        $this->formContabilizado = (bool) $despesa->contabilizado;
        $this->formAberto = true;
        $this->mensagem = '';
        $this->erro = '';
    }

    public function cancelar(): void
    {
        $this->formAberto = false;
        $this->resetErrorBag();
    }

    public function criarTipo(): void
    {
        $descricao = trim($this->novoTipo);

        if ($descricao === '') {
            $this->addError('novoTipo', 'Informe a descrição do tipo.');

            return;
        }

        $tipo = DespesaTipo::query()->firstOrCreate(['descricao' => $descricao]);

        $this->formTipoId = $tipo->id;
        $this->novoTipo = '';
        $this->resetErrorBag('novoTipo');
    }

    public function salvar(SalvarDespesa $acao): void
    {
        $this->validate();

        try {
            $valor = DinheiroBr::paraDecimal($this->formValor);
        } catch (\InvalidArgumentException) {
            $this->addError('formValor', 'Valor monetário inválido.');

            return;
        }

        $despesa = $this->despesaId !== null
            ? Despesa::query()->findOrFail($this->despesaId)
            : null;

        try {
            $acao->executar([
                'despesa_tipo_id' => $this->formTipoId,
                'data' => $this->formData,
                'descricao' => $this->formDescricao,
                'valor' => $valor,
                'contabilizado' => $this->formContabilizado,
            ], auth()->user(), $despesa);
        } catch (AuthorizationException) {
            $this->erro = 'Acesso não autorizado ou data inválida';

            return;
        }

        $this->formAberto = false;
        $this->erro = '';
        $this->mensagem = $despesa === null
            ? 'Despesa lançada com sucesso'
            : 'Alteração realizada com sucesso';
    }

    /**
     * Reproduz os 4 ramos da busca do legado (DespesaController::index):
     * numérico → valor; nome de mês PT-BR → mês; DD/MM/AAAA → data; texto → LIKE.
     */
    private function aplicarBusca(Builder $query): Builder
    {
        $busca = trim($this->busca);

        if ($busca === '') {
            return $query->whereYear('data', $this->ano);
        }

        $s = str_replace(',', '.', str_replace('.', '', $busca));

        // Ramo 1: valor exato
        if (is_numeric($s)) {
            return $query->where('valor', $s);
        }

        // Ramo 2: nome de mês PT-BR
        $mes = array_search($s, self::MESES, false);

        if ($mes !== false) {
            $like = '%'.str_replace(' ', '%', $busca).'%';

            return $query
                ->where(function (Builder $q) use ($mes, $like) {
                    $q->whereMonth('data', $mes)
                        ->orWhere('descricao', 'like', $like);
                })
                ->whereYear('data', $this->ano);
        }

        // Ramo 3: data DD/MM/AAAA
        if (preg_match('@^(\d\d)/(\d\d)/(\d\d\d\d)$@', $busca, $d) === 1
            && checkdate((int) $d[2], (int) $d[1], (int) $d[3])) {
            return $query->whereDate('data', $d[3].'-'.$d[2].'-'.$d[1]);
        }

        // Ramo 4: texto — LIKE em tipo/descrição
        $like = '%'.str_replace(' ', '%', $busca).'%';
        $despesaTipo = DespesaTipo::query()->where('descricao', 'like', $like)->first();

        if ($despesaTipo !== null) {
            return $query->where(function (Builder $q) use ($like, $despesaTipo) {
                $q->where('descricao', 'like', $like)
                    ->orWhere('despesa_tipo_id', $despesaTipo->id);
            });
        }

        return $query
            ->where('descricao', 'like', $like)
            ->whereYear('data', $this->ano);
    }

    public function render()
    {
        $query = $this->aplicarBusca(Despesa::query()->with('tipo'))
            ->when($this->tipo !== 0, fn ($q) => $q->where('despesa_tipo_id', $this->tipo));

        $total = (clone $query)->sum('valor');

        $tipos = DespesaTipo::query()->orderBy('descricao')->pluck('descricao', 'id');

        return view('livewire.financeiro.despesas.listagem', [
            'despesas' => $query->orderBy('data', 'DESC')->orderBy('descricao', 'DESC')->paginate(15),
            'total' => $total,
            'tipos' => $tipos,
        ]);
    }
}
