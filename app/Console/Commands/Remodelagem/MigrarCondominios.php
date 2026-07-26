<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Support\ParametrosCondominio;
use Illuminate\Support\Facades\DB;

/**
 * Passo 1 — cria o condomínio único (02-mapeamento-de-para.md §2: não existe
 * condomínio no schema antigo; todas as entidades migradas apontam para ele).
 */
class MigrarCondominios extends ComandoRemodelagem
{
    protected $signature = 'migrar:condominios {--truncar}';

    protected $description = 'Remodelagem: cria o condomínio único do schema novo';

    protected function tabelasDestino(): array
    {
        return ['condominios'];
    }

    protected function entidadesMapa(): array
    {
        return [];
    }

    protected function executar(): int
    {
        $nome = ParametrosCondominio::nomeCondominio();

        DB::table('condominios')->insert([
            'nome' => $nome,
            'cnpj' => null,
            'endereco' => null,
            'cidade' => null,
            'uf' => null,
            'cep' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->log("Condomínio único criado: \"{$nome}\" (nome vindo de parametros.nome_condominio).");

        return self::SUCCESS;
    }
}
