<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\PapelUsuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Passo do CUTOVER (Fase 4 — módulo Acesso, 03-modelo-dados.md): remapeia o
 * papel legado level_one → sindico. NÃO roda no migrar:remodelagem: executar
 * apenas no cutover, junto com a troca definitiva para as telas novas — antes
 * disso mudaria o comportamento das Policies do sistema em produção
 * (regra data_corte_level_one, aposentada neste momento).
 */
class RemapearPapeis extends Command
{
    protected $signature = 'migrar:remapear-papeis {--reverter : sindico → level_one (rollback do cutover)}';

    protected $description = 'Cutover: remapeia users.papel level_one → sindico (reversível com --reverter)';

    public function handle(): int
    {
        [$de, $para] = $this->option('reverter')
            ? [PapelUsuario::Sindico->value, PapelUsuario::LevelOne->value]
            : [PapelUsuario::LevelOne->value, PapelUsuario::Sindico->value];

        $afetados = DB::table('users')->where('papel', $de)->update(['papel' => $para]);

        $this->info("users.papel: {$afetados} usuário(s) remapeado(s) de '{$de}' para '{$para}'.");

        return self::SUCCESS;
    }
}
