<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Indices;

use App\Actions\Financeiro\SalvarIndiceEconomico;
use App\Enums\TipoIndiceEconomico;
use App\Models\IndiceEconomico;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestão de índices econômicos (modelo novo — substitui a tela de IPCA no
 * cutover). Suporta múltiplas séries (IPCA, IGPM, INCC); unique
 * (tipo, ano, mes) no banco. Restrito a admin (dado global).
 */
#[Layout('layouts.app')]
#[Title('Índices econômicos')]
class Gestao extends Component
{
    use WithPagination;

    #[Url(as: 'tipo', except: 'ipca')]
    public string $tipoFiltro = 'ipca';

    public bool $formAberto = false;

    public ?int $indiceId = null;

    public string $formTipo = 'ipca';

    public ?int $formAno = null;

    public ?int $formMes = null;

    public string $formIndice = '';

    public string $mensagem = '';

    public function mount(): void
    {
        $this->authorize('gerenciar', IndiceEconomico::class);
    }

    public function updatedTipoFiltro(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'formTipo' => ['required', Rule::enum(TipoIndiceEconomico::class)],
            'formAno' => ['required', 'integer', 'min:1994', 'max:2100'],
            'formMes' => ['required', 'integer', 'min:1', 'max:12'],
            'formIndice' => ['required', 'string'],
        ];
    }

    public function novoIndice(): void
    {
        $this->resetErrorBag();
        $this->indiceId = null;
        $this->formTipo = $this->tipoFiltro;
        $this->formAno = (int) date('Y');
        $this->formMes = (int) date('n');
        $this->formIndice = '';
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function editar(int $id): void
    {
        $indice = IndiceEconomico::query()->findOrFail($id);

        $this->resetErrorBag();
        $this->indiceId = $indice->id;
        $this->formTipo = $indice->tipo->value;
        $this->formAno = (int) $indice->ano;
        $this->formMes = (int) $indice->mes;
        $this->formIndice = number_format((float) $indice->indice, 4, ',', '');
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function cancelar(): void
    {
        $this->formAberto = false;
        $this->resetErrorBag();
    }

    public function salvar(SalvarIndiceEconomico $acao): void
    {
        $this->authorize('gerenciar', IndiceEconomico::class);
        $this->validate();

        $indice = str_replace(',', '.', trim($this->formIndice));

        if (! is_numeric($indice)) {
            $this->addError('formIndice', 'Índice inválido. Informe um valor numérico, ex.: 0,53.');

            return;
        }

        $registro = $this->indiceId !== null
            ? IndiceEconomico::query()->findOrFail($this->indiceId)
            : null;

        try {
            $acao->executar(
                TipoIndiceEconomico::from($this->formTipo),
                (int) $this->formAno,
                (int) $this->formMes,
                $indice,
                $registro,
            );
        } catch (UniqueConstraintViolationException) {
            $this->addError('formMes', 'Já existe um índice desta série cadastrado para este mês/ano.');

            return;
        }

        $this->formAberto = false;
        $this->mensagem = $registro === null
            ? 'Índice cadastrado com sucesso.'
            : 'Índice atualizado com sucesso.';
    }

    public function excluir(int $id): void
    {
        $this->authorize('gerenciar', IndiceEconomico::class);

        IndiceEconomico::query()->findOrFail($id)->delete();

        $this->mensagem = 'Índice excluído com sucesso.';
    }

    public function render()
    {
        return view('livewire.financeiro.indices.gestao', [
            'indices' => IndiceEconomico::query()
                ->where('tipo', $this->tipoFiltro)
                ->orderByDesc('ano')
                ->orderByDesc('mes')
                ->paginate(24),
            'tipos' => TipoIndiceEconomico::cases(),
        ]);
    }
}
