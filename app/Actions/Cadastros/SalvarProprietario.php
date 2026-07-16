<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Enums\ResponsavelPagamento;
use App\Models\Proprietario;
use App\Rules\Cpf;

class SalvarProprietario
{
    /**
     * @param array{nome: string, cpf: string, telefone: string, nome_inquilino?: ?string, cpf_inquilino?: ?string, telefone_inquilino?: ?string, responsavel_pagamento: ResponsavelPagamento} $dados
     */
    public function executar(array $dados, ?Proprietario $proprietario = null): Proprietario
    {
        // CPF gravado apenas com dígitos (decisão Q-03); DV validado no Form/Livewire
        $dados['cpf'] = Cpf::normalizar($dados['cpf']);
        if (! empty($dados['cpf_inquilino'])) {
            $dados['cpf_inquilino'] = Cpf::normalizar($dados['cpf_inquilino']);
        }

        if ($proprietario === null) {
            return Proprietario::query()->create($dados);
        }

        $proprietario->update($dados);

        return $proprietario;
    }
}
