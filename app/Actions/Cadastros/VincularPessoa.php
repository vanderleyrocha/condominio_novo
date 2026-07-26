<?php

declare(strict_types=1);

namespace App\Actions\Cadastros;

use App\Enums\PapelVinculo;
use App\Models\Pessoa;
use App\Models\Unidade;
use App\Models\UnidadePessoa;
use DomainException;
use Illuminate\Support\Facades\DB;

class VincularPessoa
{
    /**
     * Cria vínculo pessoa↔unidade. Regra de 03-modelo-dados.md: no máximo 1
     * vínculo vigente com responsavel_financeiro=true por unidade — se
     * $responsavelFinanceiro=true e já houver responsável vigente, a
     * responsabilidade é TRANSFERIDA (o vínculo anterior permanece vigente,
     * apenas deixa de ser o responsável); intenção explícita do usuário no
     * formulário.
     */
    public function executar(
        Unidade $unidade,
        Pessoa $pessoa,
        PapelVinculo $papel,
        bool $responsavelFinanceiro,
        string $dataInicio,
    ): UnidadePessoa {
        $duplicado = $unidade->vinculos()
            ->where('pessoa_id', $pessoa->id)
            ->where('papel', $papel->value)
            ->whereNull('data_fim')
            ->exists();

        if ($duplicado) {
            throw new DomainException(
                "{$pessoa->nome} já possui vínculo vigente de {$papel->rotulo()} com esta unidade."
            );
        }

        return DB::transaction(function () use ($unidade, $pessoa, $papel, $responsavelFinanceiro, $dataInicio): UnidadePessoa {
            if ($responsavelFinanceiro) {
                $unidade->vinculos()
                    ->where('responsavel_financeiro', true)
                    ->whereNull('data_fim')
                    ->update(['responsavel_financeiro' => false]);
            }

            return UnidadePessoa::query()->create([
                'unidade_id' => $unidade->id,
                'pessoa_id' => $pessoa->id,
                'papel' => $papel,
                'responsavel_financeiro' => $responsavelFinanceiro,
                'data_inicio' => $dataInicio,
                'data_fim' => null,
            ]);
        });
    }
}
