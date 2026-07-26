<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use Illuminate\Console\Command;

/**
 * CUTOVER da remodelagem (04-plano-migracao.md, Fase 4 — executar em modo de
 * manutenção, sobre o banco de produção):
 *
 *   1. migrar:remodelagem        — reconstrução final a partir do schema antigo;
 *   2. migrar:validar-remodelagem — validação profunda (aborta se divergir);
 *   3. migrar:remapear-papeis    — level_one → sindico (novo controle de acesso).
 *
 * O rename de `pagamentos_novo` → `pagamentos` fica para a Fase 5 (junto com a
 * remoção do código antigo) — decisão que mantém as telas antigas funcionais
 * durante a estabilização e torna o rollback do cutover trivial:
 * `migrar:remapear-papeis --reverter` + deploy da versão anterior
 * (as tabelas antigas permanecem intactas).
 */
class MigrarCutover extends Command
{
    protected $signature = 'migrar:cutover';

    protected $description = 'Cutover da remodelagem: ETL final + validação profunda + remap de papéis';

    public function handle(): int
    {
        $this->info('== CUTOVER da remodelagem ==');

        if ($this->call('migrar:remodelagem') !== self::SUCCESS) {
            $this->error('ETL final falhou — cutover abortado (nada foi remapeado).');

            return self::FAILURE;
        }

        if ($this->call('migrar:validar-remodelagem') !== self::SUCCESS) {
            $this->error('Validação profunda falhou — cutover abortado (nada foi remapeado).');

            return self::FAILURE;
        }

        if ($this->call('migrar:remapear-papeis') !== self::SUCCESS) {
            $this->error('Remap de papéis falhou.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Cutover concluído. Rollback: migrar:remapear-papeis --reverter + deploy da versão anterior.');

        return self::SUCCESS;
    }
}
