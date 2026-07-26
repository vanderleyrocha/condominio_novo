<?php

declare(strict_types=1);

namespace App\Livewire\Cadastros\Unidades;

use App\Actions\Cadastros\EncerrarVinculo;
use App\Actions\Cadastros\ExcluirUnidade;
use App\Actions\Cadastros\SalvarUnidade;
use App\Actions\Cadastros\VincularPessoa;
use App\Enums\PapelVinculo;
use App\Models\Pessoa;
use App\Models\Unidade;
use App\Models\UnidadePessoa;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * CRUD de unidades + gestão de vínculos pessoa↔unidade (modelo novo —
 * substitui Imóveis no cutover). Formulário inline (padrão da tela de
 * imóveis) e modal de vínculos com histórico de vigência.
 */
#[Layout('layouts.app')]
#[Title('Unidades')]
class Listagem extends Component
{
    // --- formulário de unidade (inline) ---
    public bool $exibirFormulario = false;

    public ?int $unidadeId = null;

    public string $identificacao = '';

    public ?string $fracao_ideal = null;

    public ?string $area = null;

    public int $vagas_garagem = 0;

    public ?int $confirmandoExclusaoId = null;

    // --- modal de vínculos ---
    public ?int $vinculosUnidadeId = null;

    public ?int $vinculoPessoaId = null;

    public string $vinculoPapel = 'proprietario';

    public bool $vinculoResponsavel = false;

    public string $vinculoDataInicio = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'identificacao' => [
                'required', 'string', 'max:255',
                Rule::unique('unidades', 'identificacao')->ignore($this->unidadeId)->withoutTrashed(),
            ],
            'fracao_ideal' => ['nullable', 'numeric', 'gt:0', 'lte:1'],
            'area' => ['nullable', 'numeric', 'gt:0'],
            'vagas_garagem' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }

    public function novo(): void
    {
        $this->authorize('create', Unidade::class);

        $this->reset('unidadeId', 'identificacao', 'fracao_ideal', 'area', 'vagas_garagem');
        $this->resetErrorBag();
        $this->exibirFormulario = true;
    }

    public function editar(int $id): void
    {
        $unidade = Unidade::query()->findOrFail($id);

        $this->authorize('update', $unidade);

        $this->unidadeId = $unidade->id;
        $this->identificacao = $unidade->identificacao;
        $this->fracao_ideal = $unidade->fracao_ideal;
        $this->area = $unidade->area;
        $this->vagas_garagem = $unidade->vagas_garagem;
        $this->resetErrorBag();
        $this->exibirFormulario = true;
    }

    public function cancelar(): void
    {
        $this->reset('exibirFormulario', 'unidadeId', 'identificacao', 'fracao_ideal', 'area', 'vagas_garagem');
        $this->resetErrorBag();
    }

    public function salvar(SalvarUnidade $acao): void
    {
        $unidade = $this->unidadeId !== null
            ? Unidade::query()->findOrFail($this->unidadeId)
            : null;

        $this->authorize($unidade === null ? 'create' : 'update', $unidade ?? Unidade::class);

        $dados = $this->validate();

        $acao->executar([
            'identificacao' => $dados['identificacao'],
            'fracao_ideal' => $dados['fracao_ideal'] !== null && $dados['fracao_ideal'] !== '' ? $dados['fracao_ideal'] : null,
            'area' => $dados['area'] !== null && $dados['area'] !== '' ? $dados['area'] : null,
            'vagas_garagem' => (int) $dados['vagas_garagem'],
        ], $unidade);

        session()->flash('status', $unidade === null
            ? 'Unidade cadastrada com sucesso!'
            : 'Unidade atualizada com sucesso!');

        $this->cancelar();
    }

    public function confirmarExclusao(int $id): void
    {
        $this->confirmandoExclusaoId = $id;
    }

    public function cancelarExclusao(): void
    {
        $this->confirmandoExclusaoId = null;
    }

    public function excluir(ExcluirUnidade $acao): void
    {
        if ($this->confirmandoExclusaoId === null) {
            return;
        }

        $unidade = Unidade::query()->findOrFail($this->confirmandoExclusaoId);

        $this->authorize('delete', $unidade);

        try {
            $acao->executar($unidade);
            session()->flash('status', 'Unidade removida com sucesso!');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->confirmandoExclusaoId = null;
    }

    // --- vínculos ---

    public function abrirVinculos(int $unidadeId): void
    {
        $unidade = Unidade::query()->findOrFail($unidadeId);

        $this->authorize('gerirVinculos', $unidade);

        $this->vinculosUnidadeId = $unidadeId;
        $this->reset('vinculoPessoaId', 'vinculoResponsavel');
        $this->vinculoPapel = 'proprietario';
        $this->vinculoDataInicio = now()->toDateString();
        $this->resetErrorBag();
    }

    public function fecharVinculos(): void
    {
        $this->reset('vinculosUnidadeId', 'vinculoPessoaId', 'vinculoPapel', 'vinculoResponsavel', 'vinculoDataInicio');
        $this->resetErrorBag();
    }

    public function vincular(VincularPessoa $acao): void
    {
        if ($this->vinculosUnidadeId === null) {
            return;
        }

        $unidade = Unidade::query()->findOrFail($this->vinculosUnidadeId);

        $this->authorize('gerirVinculos', $unidade);

        $dados = $this->validate([
            'vinculoPessoaId' => ['required', 'integer', Rule::exists('pessoas', 'id')->whereNull('deleted_at')],
            'vinculoPapel' => ['required', Rule::enum(PapelVinculo::class)],
            'vinculoDataInicio' => ['required', 'date'],
        ]);

        try {
            $acao->executar(
                $unidade,
                Pessoa::query()->findOrFail((int) $dados['vinculoPessoaId']),
                PapelVinculo::from($dados['vinculoPapel']),
                $this->vinculoResponsavel,
                $dados['vinculoDataInicio'],
            );

            session()->flash('status', 'Vínculo criado com sucesso!');
            $this->reset('vinculoPessoaId', 'vinculoResponsavel');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function encerrarVinculo(int $vinculoId, EncerrarVinculo $acao): void
    {
        $vinculo = UnidadePessoa::query()->findOrFail($vinculoId);

        $this->authorize('gerirVinculos', $vinculo->unidade);

        try {
            $acao->executar($vinculo);
            session()->flash('status', 'Vínculo encerrado.');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $unidadeVinculos = $this->vinculosUnidadeId !== null
            ? Unidade::query()
                ->with(['vinculos' => fn ($q) => $q->with('pessoa')->orderByRaw('data_fim IS NOT NULL')->orderByDesc('data_inicio')])
                ->find($this->vinculosUnidadeId)
            : null;

        return view('livewire.cadastros.unidades.listagem', [
            'unidades' => Unidade::query()
                ->with(['vinculos' => fn ($q) => $q->whereNull('data_fim')->with('pessoa')])
                ->withCount('taxasCondominiais')
                ->orderBy('identificacao')
                ->get(),
            'pessoas' => Pessoa::query()->orderBy('nome')->get(['id', 'nome']),
            'papeis' => PapelVinculo::cases(),
            'unidadeVinculos' => $unidadeVinculos,
        ]);
    }
}
