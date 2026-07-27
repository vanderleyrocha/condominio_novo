<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use App\Models\LancamentoFinanceiro;
use RuntimeException;

/**
 * Lançamentos financeiros no modelo novo (substitui SalvarReceita e
 * SalvarDespesa no cutover): tabela unificada com natureza + plano de contas.
 * Receita vinculada a cobrança extraordinária recebe a origem polimórfica.
 * Sem exclusão (paridade RN-29).
 */
class SalvarLancamento
{
    /**
     * @param  array{plano_conta_id: int, natureza: string, data: string, descricao: string, valor: string, contabilizado: bool, cobranca_extraordinaria_id?: ?int, finalidade_id?: ?int}  $dados
     */
    public function executar(array $dados, ?LancamentoFinanceiro $lancamento = null): LancamentoFinanceiro
    {
        $cobrancaId = $dados['cobranca_extraordinaria_id'] ?? null;

        $atributos = [
            'plano_conta_id' => $dados['plano_conta_id'],
            // Destinação da receita/despesa (05-plano §3.1); null = custeio geral
            'finalidade_id' => $dados['finalidade_id'] ?? null,
            'natureza' => $dados['natureza'],
            'data_competencia' => $dados['data'],
            'data_lancamento' => $dados['data'],
            'descricao' => $dados['descricao'],
            'valor' => $dados['valor'],
            'contabilizado' => $dados['contabilizado'],
            'origem_type' => $cobrancaId !== null ? CobrancaExtraordinaria::class : null,
            'origem_id' => $cobrancaId,
        ];

        if ($lancamento === null) {
            $condominioId = Condominio::query()->value('id')
                ?? throw new RuntimeException('Nenhum condomínio cadastrado — rode migrar:condominios.');

            return LancamentoFinanceiro::query()->create($atributos + ['condominio_id' => $condominioId]);
        }

        $lancamento->update($atributos);

        return $lancamento;
    }
}
