<?php

declare(strict_types=1);

namespace App\Console\Commands\Composicao;

use App\Models\CobrancaExtraordinaria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ETAPA 6 do plano de composição de taxas (docs/migration/05-plano-composicao-taxas.md):
 * remove `cobranca_extraordinaria_taxa`. O papel do pivô — registrar que uma
 * cobrança extraordinária incide sobre uma competência — passou para
 * `itens_taxa_condominial`, que ainda compõe o valor devido (invariante §3.4),
 * carrega finalidade e plano de contas, e é discriminado ao condômino.
 *
 * É um COMANDO e não uma migration, pelo mesmo motivo de migrar:descomissionar:
 * um `php artisan migrate` nunca deve dropar dado histórico automaticamente —
 * e a suíte de testes precisa do pivô para exercitar a detecção da
 * inconsistência N-02.
 *
 * GUARDA: aborta se alguma linha do pivô não tiver item equivalente
 * (`composicao:conferir-pivo`). Depois do drop, o conteúdo só existe nos
 * backups. IRREVERSÍVEL.
 */
class DescomissionarPivoCobranca extends Command
{
    protected $signature = 'composicao:descomissionar-pivo {--forcar : não pedir confirmação}';

    protected $description = 'Etapa 6: remove cobranca_extraordinaria_taxa após conferir que os itens o substituem';

    public function handle(): int
    {
        if (! Schema::hasTable('cobranca_extraordinaria_taxa')) {
            $this->info('Nada a fazer: o pivô já foi descomissionado.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('itens_taxa_condominial')) {
            $this->error('itens_taxa_condominial não existe — rode as migrations e a Etapa 3 antes.');

            return self::FAILURE;
        }

        $orfas = DB::table('cobranca_extraordinaria_taxa as p')
            ->leftJoin('itens_taxa_condominial as i', function ($join): void {
                $join->on('i.taxa_condominial_id', '=', 'p.taxa_condominial_id')
                    ->on('i.origem_id', '=', 'p.cobranca_extraordinaria_id')
                    ->where('i.origem_type', CobrancaExtraordinaria::class)
                    ->whereNull('i.deleted_at');
            })
            ->whereNull('i.id')
            ->count();

        if ($orfas > 0) {
            $this->error(
                "Abortado: {$orfas} linha(s) do pivô não têm item equivalente. "
                .'Rode `php artisan composicao:conferir-pivo`, decida as exceções e tente de novo.'
            );

            return self::FAILURE;
        }

        $total = DB::table('cobranca_extraordinaria_taxa')->count();

        if (! $this->option('forcar') && ! $this->confirm(
            "IRREVERSÍVEL: {$total} linha(s) de cobranca_extraordinaria_taxa serão removidas com a tabela. Continuar?"
        )) {
            $this->info('Cancelado.');

            return self::FAILURE;
        }

        Schema::dropIfExists('cobranca_extraordinaria_taxa');

        $this->info('Pivô cobranca_extraordinaria_taxa removido. A composição vive em itens_taxa_condominial.');
        $this->warn(
            'Remova agora as relações marcadas @deprecated: CobrancaExtraordinaria::taxasCondominiais() '
            .'e TaxaCondominial::cobrancasExtraordinarias().'
        );

        return self::SUCCESS;
    }
}
