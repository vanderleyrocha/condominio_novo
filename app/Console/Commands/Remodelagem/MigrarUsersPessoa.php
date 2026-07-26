<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use Illuminate\Support\Facades\DB;

/**
 * Passo 12 — backfill de users.pessoa_id (match por e-mail; o legado não tem
 * CPF em users) e vínculo de todos os users ao condomínio único em
 * condominio_user (02-mapeamento-de-para.md §10).
 *
 * users.papel NÃO é alterado aqui: a decisão (level_one → sindico) está tomada,
 * mas o remap acontece só no cutover (Fase 4), junto com a reescrita das
 * Policies — antes disso mudaria o comportamento do sistema em produção.
 */
class MigrarUsersPessoa extends ComandoRemodelagem
{
    protected $signature = 'migrar:users-pessoa {--truncar}';

    protected $description = 'Remodelagem: vincula users a pessoas e ao condomínio único';

    protected function tabelasDestino(): array
    {
        return ['condominio_user'];
    }

    protected function entidadesMapa(): array
    {
        return [];
    }

    protected function executar(): int
    {
        $condominioId = $this->condominioId();

        $pessoasPorEmail = DB::table('pessoas')
            ->whereNotNull('email')
            ->pluck('id', 'email')
            ->all();

        $matches = 0;
        $linhas = [];

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $pessoaId = $pessoasPorEmail[$user->email] ?? null;

            if ($pessoaId !== null) {
                DB::table('users')->where('id', $user->id)->update(['pessoa_id' => $pessoaId]);
                $matches++;
            }

            $linhas[] = [
                'condominio_id' => $condominioId,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($linhas, self::CHUNK) as $chunk) {
            DB::table('condominio_user')->insert($chunk);
        }

        $this->log(count($linhas)." user(s) vinculados ao condomínio único; pessoa_id preenchido por e-mail: {$matches}.");
        $this->log("Papel 'level_one' mantido até o cutover — remap definido: level_one → sindico (Fase 4).");

        return self::SUCCESS;
    }
}
