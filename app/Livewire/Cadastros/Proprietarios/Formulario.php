<?php

declare(strict_types=1);

namespace App\Livewire\Cadastros\Proprietarios;

use App\Actions\Cadastros\SalvarProprietario;
use App\Enums\ResponsavelPagamento;
use App\Models\Proprietario;
use App\Rules\Cpf;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formulário único para cadastro e edição de proprietários
 * (telas proprietarios.cadastro e proprietarios.edicao).
 */
#[Layout('layouts.app')]
class Formulario extends Component
{
    public ?Proprietario $proprietario = null;

    public string $nome = '';

    public string $cpf = '';

    public string $telefone = '';

    public ?string $nome_inquilino = null;

    public ?string $cpf_inquilino = null;

    public ?string $telefone_inquilino = null;

    public string $responsavel_pagamento = 'proprietario';

    public function mount(?Proprietario $proprietario = null): void
    {
        if ($proprietario === null || ! $proprietario->exists) {
            $this->authorize('create', Proprietario::class);

            return;
        }

        $this->authorize('update', $proprietario);

        $this->proprietario = $proprietario;
        $this->nome = $proprietario->nome;
        $this->cpf = $this->mascararCpf($proprietario->cpf);
        $this->telefone = $proprietario->telefone;
        $this->nome_inquilino = $proprietario->nome_inquilino;
        $this->cpf_inquilino = $proprietario->cpf_inquilino !== null
            ? $this->mascararCpf($proprietario->cpf_inquilino)
            : null;
        $this->telefone_inquilino = $proprietario->telefone_inquilino;
        $this->responsavel_pagamento = $proprietario->responsavel_pagamento->value;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => [
                'required',
                new Cpf,
                Rule::unique('proprietarios', 'cpf')->ignore($this->proprietario?->id),
            ],
            'telefone' => ['required', 'string', 'max:20'],
            'nome_inquilino' => ['nullable', 'string', 'max:255'],
            'cpf_inquilino' => ['nullable', new Cpf],
            'telefone_inquilino' => ['nullable', 'string', 'max:20'],
            'responsavel_pagamento' => ['required', Rule::enum(ResponsavelPagamento::class)],
        ];
    }

    public function salvar(SalvarProprietario $acao): void
    {
        // Normaliza antes de validar para que unique/Cpf operem sobre os dígitos (Q-03)
        $this->cpf = Cpf::normalizar($this->cpf);
        if ($this->cpf_inquilino !== null && $this->cpf_inquilino !== '') {
            $this->cpf_inquilino = Cpf::normalizar($this->cpf_inquilino);
        } else {
            $this->cpf_inquilino = null;
        }

        $dados = $this->validate();

        $dados['responsavel_pagamento'] = ResponsavelPagamento::from($this->responsavel_pagamento);

        $edicao = $this->proprietario !== null;

        $acao->executar($dados, $this->proprietario);

        session()->flash('status', $edicao
            ? 'Proprietário atualizado com sucesso!'
            : 'Proprietário cadastrado com sucesso!');

        $this->redirectRoute('proprietarios.index', navigate: true);
    }

    private function mascararCpf(string $cpf): string
    {
        if (strlen($cpf) !== 11) {
            return $cpf;
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($cpf, 0, 3),
            substr($cpf, 3, 3),
            substr($cpf, 6, 3),
            substr($cpf, 9, 2),
        );
    }

    public function render()
    {
        return view('livewire.cadastros.proprietarios.formulario', [
            'responsaveis' => ResponsavelPagamento::cases(),
        ]);
    }
}
