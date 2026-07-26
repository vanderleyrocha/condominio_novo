<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Models\Pessoa;
use App\Rules\CpfOuCnpj;

class SalvarPessoa
{
    /**
     * @param  array{nome: string, cpf_cnpj?: ?string, email?: ?string, telefone?: ?string, tipo: string}  $dados
     */
    public function executar(array $dados, ?Pessoa $pessoa = null): Pessoa
    {
        // Documento gravado apenas com dígitos (Q-03); DV validado no Livewire
        $dados['cpf_cnpj'] = ! empty($dados['cpf_cnpj'])
            ? CpfOuCnpj::normalizar($dados['cpf_cnpj'])
            : null;

        if ($pessoa === null) {
            return Pessoa::query()->create($dados);
        }

        $pessoa->update($dados);

        return $pessoa;
    }
}
