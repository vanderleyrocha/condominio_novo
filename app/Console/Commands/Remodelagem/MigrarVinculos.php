<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\PapelVinculo;
use App\Support\Remodelagem\MapaIds;
use Illuminate\Support\Facades\DB;

/**
 * Passo 4 — cria unidade_pessoa (02-mapeamento-de-para.md §1):
 * papel proprietário sempre; inquilino quando existir; responsavel_financeiro
 * conforme proprietarios.responsavel_pagamento; data_inicio = created_at do
 * proprietario (fallback — não há data melhor no legado).
 *
 * O inquilino do legado é atributo do PROPRIETÁRIO (não do imóvel) — se o
 * proprietário tem mais de um imóvel, o vínculo de inquilino é replicado em
 * todos e o caso é logado para revisão manual.
 */
class MigrarVinculos extends ComandoRemodelagem
{
    protected $signature = 'migrar:vinculos {--truncar}';

    protected $description = 'Remodelagem: vínculos pessoa↔unidade (unidade_pessoa)';

    protected function tabelasDestino(): array
    {
        return ['unidade_pessoa'];
    }

    protected function entidadesMapa(): array
    {
        return [];
    }

    protected function executar(): int
    {
        $mapaPessoa = MapaIds::carregar('pessoa');
        $mapaInquilino = MapaIds::carregar('pessoa_inquilino');
        $imoveisPorProprietario = DB::table('imoveis')
            ->select('id', 'proprietario_id')
            ->get()
            ->groupBy('proprietario_id');

        $vinculos = 0;
        $semImovel = 0;
        $inquilinoReplicado = 0;
        $responsavelAjustado = 0;

        DB::table('proprietarios')->orderBy('id')->chunk(self::CHUNK, function ($proprietarios) use (
            $mapaPessoa, $mapaInquilino, $imoveisPorProprietario,
            &$vinculos, &$semImovel, &$inquilinoReplicado, &$responsavelAjustado
        ): void {
            $linhas = [];

            foreach ($proprietarios as $p) {
                $imoveis = $imoveisPorProprietario->get($p->id);

                if ($imoveis === null) {
                    $semImovel++;

                    continue;
                }

                $pessoaProprietario = $mapaPessoa[$p->id]
                    ?? throw new \RuntimeException("Proprietario {$p->id} sem pessoa mapeada — rode migrar:pessoas.");
                $pessoaInquilino = $mapaInquilino[$p->id] ?? null;
                $responsavelInquilino = $p->responsavel_pagamento === 'inquilino';

                // Responsável 'inquilino' sem inquilino cadastrado: proprietário assume
                if ($responsavelInquilino && $pessoaInquilino === null) {
                    $responsavelInquilino = false;
                    $responsavelAjustado++;
                }

                if ($pessoaInquilino !== null && count($imoveis) > 1) {
                    $inquilinoReplicado++;
                    $this->log(
                        "Proprietario {$p->id} tem inquilino e ".count($imoveis)
                        .' imóveis — vínculo de inquilino replicado em todos (revisar manualmente).'
                    );
                }

                $dataInicio = substr((string) ($p->created_at ?? now()->toDateTimeString()), 0, 10);

                foreach ($imoveis as $imovel) {
                    $linhas[] = $this->linha($imovel->id, $pessoaProprietario, PapelVinculo::Proprietario, ! $responsavelInquilino, $dataInicio, $p);

                    if ($pessoaInquilino !== null) {
                        $linhas[] = $this->linha($imovel->id, $pessoaInquilino, PapelVinculo::Inquilino, $responsavelInquilino, $dataInicio, $p);
                    }
                }
            }

            DB::table('unidade_pessoa')->insert($linhas);
            $vinculos += count($linhas);
        });

        $this->log("Vínculos criados: {$vinculos}.");

        if ($semImovel > 0) {
            $this->log("Proprietários sem imóvel (pessoa criada, sem vínculo): {$semImovel}.");
        }

        if ($responsavelAjustado > 0) {
            $this->log("Responsável 'inquilino' sem inquilino cadastrado (proprietário assumiu): {$responsavelAjustado}.");
        }

        return self::SUCCESS;
    }

    private function linha(int $unidadeId, int $pessoaId, PapelVinculo $papel, bool $responsavel, string $dataInicio, object $p): array
    {
        return [
            'unidade_id' => $unidadeId,
            'pessoa_id' => $pessoaId,
            'papel' => $papel->value,
            'responsavel_financeiro' => $responsavel,
            'data_inicio' => $dataInicio,
            'data_fim' => null,
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ];
    }
}
