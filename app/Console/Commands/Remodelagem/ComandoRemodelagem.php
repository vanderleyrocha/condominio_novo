<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Support\Remodelagem\MapaIds;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base dos commands da Fase 2 da remodelagem (04-plano-migracao.md).
 *
 * Idempotência por reconstrução: o orquestrador `migrar:remodelagem` trunca
 * todas as tabelas novas e executa os passos na ordem; cada command isolado
 * exige destino vazio (ou `--truncar` para limpar apenas o próprio destino).
 * Sem transação externa — qualquer falha, re-executar do zero.
 */
abstract class ComandoRemodelagem extends Command
{
    protected const CHUNK = 500;

    /** @var list<string> */
    protected array $relatorio = [];

    /**
     * Tabelas novas que este passo popula (guarda de destino vazio + --truncar).
     *
     * @return list<string>
     */
    abstract protected function tabelasDestino(): array;

    /**
     * Entidades registradas em migration_id_map por este passo.
     *
     * @return list<string>
     */
    abstract protected function entidadesMapa(): array;

    abstract protected function executar(): int;

    public function handle(): int
    {
        if ($this->hasOption('truncar') && $this->option('truncar')) {
            $this->truncarDestino();
        }

        foreach ($this->tabelasDestino() as $tabela) {
            if (DB::table($tabela)->exists()) {
                $this->error(
                    "Tabela `{$tabela}` não está vazia. Rode `php artisan migrar:remodelagem` "
                    .'(reconstrução completa) ou repita este passo com --truncar.'
                );

                return self::FAILURE;
            }
        }

        $resultado = $this->executar();

        foreach ($this->relatorio as $linha) {
            $this->line('  - '.$linha);
        }

        return $resultado;
    }

    protected function truncarDestino(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->tabelasDestino() as $tabela) {
            DB::table($tabela)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        MapaIds::limpar(...$this->entidadesMapa());
    }

    protected function log(string $mensagem): void
    {
        $this->relatorio[] = $mensagem;
    }

    /**
     * Id do condomínio único criado por migrar:condominios.
     */
    protected function condominioId(): int
    {
        $id = DB::table('condominios')->value('id');

        if ($id === null) {
            throw new \RuntimeException('Nenhum condomínio encontrado — rode migrar:condominios antes.');
        }

        return (int) $id;
    }
}
