<?php

declare(strict_types=1);

namespace App\Livewire\Cadastros\Imoveis;

use App\Actions\Cadastros\ExcluirImovel;
use App\Actions\Cadastros\SalvarImovel;
use App\Models\Imovel;
use App\Models\Proprietario;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * CRUD de imóveis — funcionalidade nova (BR-HUMANA-002), restrita a admin
 * via ImovelPolicy; listagem visível a autenticados.
 */
#[Layout('layouts.app')]
class Listagem extends Component
{
    public bool $exibirFormulario = false;

    public ?int $imovelId = null;

    public string $nome = '';

    public ?int $proprietario_id = null;

    public ?int $confirmandoExclusaoId = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('imoveis', 'nome')->ignore($this->imovelId),
            ],
            'proprietario_id' => ['required', 'integer', Rule::exists('proprietarios', 'id')],
        ];
    }

    public function novo(): void
    {
        $this->authorize('create', Imovel::class);

        $this->reset('imovelId', 'nome', 'proprietario_id');
        $this->resetErrorBag();
        $this->exibirFormulario = true;
    }

    public function editar(int $id): void
    {
        $imovel = Imovel::query()->findOrFail($id);

        $this->authorize('update', $imovel);

        $this->imovelId = $imovel->id;
        $this->nome = $imovel->nome;
        $this->proprietario_id = $imovel->proprietario_id;
        $this->resetErrorBag();
        $this->exibirFormulario = true;
    }

    public function cancelar(): void
    {
        $this->reset('exibirFormulario', 'imovelId', 'nome', 'proprietario_id');
        $this->resetErrorBag();
    }

    public function salvar(SalvarImovel $acao): void
    {
        $imovel = $this->imovelId !== null
            ? Imovel::query()->findOrFail($this->imovelId)
            : null;

        $this->authorize($imovel === null ? 'create' : 'update', $imovel ?? Imovel::class);

        $dados = $this->validate();

        try {
            $acao->executar([
                'nome' => $dados['nome'],
                'proprietario_id' => (int) $dados['proprietario_id'],
            ], $imovel);

            session()->flash('status', $imovel === null
                ? 'Imóvel cadastrado com sucesso!'
                : 'Imóvel atualizado com sucesso!');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

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

    public function excluir(ExcluirImovel $acao): void
    {
        if ($this->confirmandoExclusaoId === null) {
            return;
        }

        $imovel = Imovel::query()->findOrFail($this->confirmandoExclusaoId);

        $this->authorize('delete', $imovel);

        try {
            $acao->executar($imovel);
            session()->flash('status', 'Imóvel removido com sucesso!');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->confirmandoExclusaoId = null;
    }

    public function render()
    {
        return view('livewire.cadastros.imoveis.listagem', [
            'imoveis' => Imovel::query()
                ->with('proprietario')
                ->withCount('mensalidades')
                ->orderBy('nome')
                ->get(),
            'proprietarios' => Proprietario::query()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }
}
