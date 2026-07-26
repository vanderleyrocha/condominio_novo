<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 5 — descomissionamento do schema antigo (04-plano-migracao.md).
 * Executar APÓS o migrar:cutover, na mesma janela de manutenção ou depois da
 * estabilização:
 *
 *   1. dropa as tabelas do schema antigo + migration_id_map;
 *   2. renomeia pagamentos_novo → pagamentos (nome definitivo).
 *
 * É um comando (não migration) para nunca rodar automaticamente num
 * `php artisan migrate` antes do ETL do cutover. Guardas: exige o schema novo
 * populado e o remap de papéis executado. IRREVERSÍVEL — os dados antigos
 * passam a existir apenas nos backups e no servidor remoto legado.
 */
class MigrarDescomissionar extends Command
{
    /** @var list<string> ordem irrelevante — FKs desabilitadas */
    private const TABELAS_ANTIGAS = [
        'pagamento_mensalidades',
        'cobranca_extra_mensalidade',
        'receitas',
        'cobrancas_extras',
        'despesas',
        'despesa_tipos',
        'mensalidades',
        'pagamentos',
        'imoveis',
        'proprietarios',
        'ipcas',
        'parametros',
        'migration_id_map',
    ];

    protected $signature = 'migrar:descomissionar {--forcar : não pedir confirmação}';

    protected $description = 'Fase 5: dropa o schema antigo e renomeia pagamentos_novo → pagamentos (irreversível)';

    public function handle(): int
    {
        if (! Schema::hasTable('pagamentos_novo')) {
            $this->info('Nada a fazer: descomissionamento já executado (pagamentos_novo não existe).');

            return self::SUCCESS;
        }

        if (! DB::table('taxas_condominiais')->exists()) {
            $this->error('Schema novo vazio — rode migrar:cutover antes de descomissionar.');

            return self::FAILURE;
        }

        if (DB::table('users')->where('papel', 'level_one')->exists()) {
            $this->error('Ainda há usuários level_one — rode migrar:cutover (remap de papéis) antes.');

            return self::FAILURE;
        }

        if (! $this->option('forcar') && ! $this->confirm(
            'IRREVERSÍVEL: as tabelas do schema antigo serão removidas deste banco. Continuar?'
        )) {
            $this->info('Cancelado.');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();

        foreach (self::TABELAS_ANTIGAS as $tabela) {
            Schema::dropIfExists($tabela);
            $this->line("  - drop {$tabela}");
        }

        DB::statement('RENAME TABLE pagamentos_novo TO pagamentos');
        $this->line('  - rename pagamentos_novo → pagamentos');

        Schema::enableForeignKeyConstraints();

        $this->info('Descomissionamento concluído — o schema novo é o único do banco.');

        return self::SUCCESS;
    }
}
