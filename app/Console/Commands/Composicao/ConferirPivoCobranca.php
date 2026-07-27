<?php

declare(strict_types=1);

namespace App\Console\Commands\Composicao;

use App\Models\CobrancaExtraordinaria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 6 de docs/migration/05-plano-composicao-taxas.md — gate do
 * descomissionamento de `cobranca_extraordinaria_taxa`: confere que todo
 * conteúdo do pivô está representado nos itens da composição antes de a
 * migration remover a tabela.
 *
 * Sai com código ≠ 0 se houver linha do pivô sem item equivalente.
 * A inconsistência N-02 (pivô apontando para taxa sem o valor embutido) aparece
 * aqui como divergência esperada — ela existe no dado de origem, não no
 * descomissionamento, e é justamente o que o relatório do ETL já reportou.
 */
class ConferirPivoCobranca extends Command
{
    protected $signature = 'composicao:conferir-pivo {--limite=20 : Máximo de divergências listadas}';

    protected $description = 'Etapa 6: confere se cobranca_extraordinaria_taxa já está representado nos itens';

    public function handle(): int
    {
        if (! Schema::hasTable('cobranca_extraordinaria_taxa')) {
            $this->info('Pivô já descomissionado — nada a conferir.');

            return self::SUCCESS;
        }

        $total = DB::table('cobranca_extraordinaria_taxa')->count();

        // Linha do pivô sem item correspondente (mesma taxa, mesma origem)
        $orfas = DB::table('cobranca_extraordinaria_taxa as p')
            ->join('cobrancas_extraordinarias as c', 'c.id', '=', 'p.cobranca_extraordinaria_id')
            ->leftJoin('itens_taxa_condominial as i', function ($join): void {
                $join->on('i.taxa_condominial_id', '=', 'p.taxa_condominial_id')
                    ->on('i.origem_id', '=', 'p.cobranca_extraordinaria_id')
                    ->where('i.origem_type', CobrancaExtraordinaria::class)
                    ->whereNull('i.deleted_at');
            })
            ->whereNull('i.id')
            ->orderBy('p.id')
            ->get([
                'p.taxa_condominial_id', 'p.valor', 'c.nome',
            ]);

        $this->table(['Verificação', 'Resultado'], [
            ['linhas no pivô', (string) $total],
            ['sem item equivalente', (string) $orfas->count()],
        ]);

        if ($orfas->isEmpty()) {
            $this->info('Pivô integralmente representado nos itens — seguro descomissionar.');

            return self::SUCCESS;
        }

        $this->warn($orfas->count().' linha(s) do pivô sem item equivalente:');

        foreach ($orfas->take((int) $this->option('limite')) as $linha) {
            $this->line(sprintf(
                '  * taxa #%d · %s · R$ %s',
                $linha->taxa_condominial_id, $linha->nome, $linha->valor,
            ));
        }

        $this->line('');
        $this->line('Se forem as exceções já reportadas por `taxas:decompor-composicao`, decida-as');
        $this->line('antes de remover a tabela — depois do drop o registro do pivô só existe nos backups.');

        return self::FAILURE;
    }
}
