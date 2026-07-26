<?php

declare(strict_types=1);

namespace App\Livewire\Cadastros\Pessoas;

use App\Actions\Cadastros\SalvarPessoa;
use App\Enums\TipoPessoa;
use App\Models\Pessoa;
use App\Rules\CpfOuCnpj;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Formulário único para cadastro e edição de pessoas (modelo novo).
 * Documento opcional (inquilinos legados podem não ter CPF), mas quando
 * informado valida DV de CPF ou CNPJ e unicidade.
 */
#[Layout('layouts.app')]
#[Title('Cadastro de pessoa')]
class Formulario extends Component
{
    public ?Pessoa $pessoa = null;

    public string $nome = '';

    public string $tipo = 'fisica';

    public ?string $cpf_cnpj = null;

    public ?string $email = null;

    public ?string $telefone = null;

    public function mount(?Pessoa $pessoa = null): void
    {
        if ($pessoa === null || ! $pessoa->exists) {
            $this->authorize('create', Pessoa::class);

            return;
        }

        $this->authorize('update', $pessoa);

        $this->pessoa = $pessoa;
        $this->nome = $pessoa->nome;
        $this->tipo = $pessoa->tipo->value;
        $this->cpf_cnpj = $pessoa->cpf_cnpj;
        $this->email = $pessoa->email;
        $this->telefone = $pessoa->telefone;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoPessoa::class)],
            'cpf_cnpj' => [
                'nullable',
                new CpfOuCnpj,
                Rule::unique('pessoas', 'cpf_cnpj')->ignore($this->pessoa?->id)->withoutTrashed(),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function salvar(SalvarPessoa $acao): void
    {
        // Normaliza antes de validar para que unique/DV operem sobre os dígitos (Q-03)
        $this->cpf_cnpj = ($this->cpf_cnpj !== null && trim($this->cpf_cnpj) !== '')
            ? CpfOuCnpj::normalizar($this->cpf_cnpj)
            : null;

        $dados = $this->validate();

        $edicao = $this->pessoa !== null;

        $acao->executar($dados, $this->pessoa);

        session()->flash('status', $edicao
            ? 'Pessoa atualizada com sucesso!'
            : 'Pessoa cadastrada com sucesso!');

        $this->redirectRoute('pessoas.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.cadastros.pessoas.formulario', [
            'tipos' => TipoPessoa::cases(),
        ]);
    }
}
