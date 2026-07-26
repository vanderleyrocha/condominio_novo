<?php

declare(strict_types=1);

namespace App\Console\Commands\Remodelagem;

use App\Enums\TipoPessoa;
use App\Support\Remodelagem\MapaIds;
use Illuminate\Support\Facades\DB;

/**
 * Passo 2 — proprietarios → pessoas, com DEDUPLICAÇÃO por CPF
 * (02-mapeamento-de-para.md §1): pessoas.cpf_cnpj é unique; o mesmo CPF
 * aparecendo como proprietário de várias unidades ou como inquilino em outra
 * reaproveita o registro. Mapa N:1 em migration_id_map:
 *   - entidade `pessoa`: proprietario.id -> pessoa do proprietário
 *   - entidade `pessoa_inquilino`: proprietario.id -> pessoa do inquilino
 *
 * Os vínculos com unidades são criados depois por migrar:vinculos.
 */
class MigrarPessoas extends ComandoRemodelagem
{
    protected $signature = 'migrar:pessoas {--truncar}';

    protected $description = 'Remodelagem: proprietários e inquilinos → pessoas (dedupe por CPF)';

    /** @var array<string, array{id: int, nome: string, telefone: ?string, updated_at: ?string}> */
    private array $porCpf = [];

    private int $criadas = 0;

    private int $reaproveitadas = 0;

    private int $semDocumento = 0;

    private int $divergencias = 0;

    protected function tabelasDestino(): array
    {
        return ['pessoas'];
    }

    protected function entidadesMapa(): array
    {
        return ['pessoa', 'pessoa_inquilino'];
    }

    protected function executar(): int
    {
        DB::table('proprietarios')->orderBy('id')->chunk(self::CHUNK, function ($proprietarios): void {
            $mapaProprietario = [];
            $mapaInquilino = [];

            foreach ($proprietarios as $p) {
                $mapaProprietario[] = [
                    'id_antigo' => (int) $p->id,
                    'id_novo' => $this->criarOuReaproveitar($p->cpf, $p->nome, $p->telefone, $p->created_at, $p->updated_at),
                ];

                if ($p->nome_inquilino !== null && trim($p->nome_inquilino) !== '') {
                    $mapaInquilino[] = [
                        'id_antigo' => (int) $p->id,
                        'id_novo' => $this->criarOuReaproveitar(
                            $p->cpf_inquilino, $p->nome_inquilino, $p->telefone_inquilino, $p->created_at, $p->updated_at
                        ),
                    ];
                }
            }

            MapaIds::registrarLote('pessoa', $mapaProprietario);
            MapaIds::registrarLote('pessoa_inquilino', $mapaInquilino);
        });

        $this->log("Pessoas criadas: {$this->criadas} (reaproveitadas por CPF: {$this->reaproveitadas}).");

        if ($this->semDocumento > 0) {
            $this->log("Pessoas sem documento (inquilino legado sem CPF): {$this->semDocumento} — sanear posteriormente.");
        }

        if ($this->divergencias > 0) {
            $this->log("Divergências de nome/telefone no mesmo CPF: {$this->divergencias} (prevaleceu o registro mais recente).");
        }

        return self::SUCCESS;
    }

    private function criarOuReaproveitar(
        ?string $cpf,
        string $nome,
        ?string $telefone,
        ?string $createdAt,
        ?string $updatedAt,
    ): int {
        $cpf = $cpf !== null ? preg_replace('/\D/', '', $cpf) : null;

        if ($cpf === null || $cpf === '') {
            $this->semDocumento++;

            return $this->inserir(null, $nome, $telefone, $createdAt, $updatedAt);
        }

        $existente = $this->porCpf[$cpf] ?? null;

        if ($existente === null) {
            $id = $this->inserir($cpf, $nome, $telefone, $createdAt, $updatedAt);
            $this->porCpf[$cpf] = ['id' => $id, 'nome' => $nome, 'telefone' => $telefone, 'updated_at' => $updatedAt];

            return $id;
        }

        $this->reaproveitadas++;

        if ($existente['nome'] !== $nome || $existente['telefone'] !== $telefone) {
            $this->divergencias++;
            $this->log(
                "CPF {$cpf}: dados divergentes entre registros do legado "
                ."(\"{$existente['nome']}\" vs \"{$nome}\")."
            );

            // Prevalece o registro mais recente (02-mapeamento-de-para.md §1)
            if ($updatedAt !== null && ($existente['updated_at'] === null || $updatedAt > $existente['updated_at'])) {
                DB::table('pessoas')->where('id', $existente['id'])
                    ->update(['nome' => $nome, 'telefone' => $telefone, 'updated_at' => $updatedAt]);
                $this->porCpf[$cpf] = ['id' => $existente['id'], 'nome' => $nome, 'telefone' => $telefone, 'updated_at' => $updatedAt];
            }
        }

        return $existente['id'];
    }

    private function inserir(?string $cpf, string $nome, ?string $telefone, ?string $createdAt, ?string $updatedAt): int
    {
        $this->criadas++;

        return (int) DB::table('pessoas')->insertGetId([
            'nome' => $nome,
            'cpf_cnpj' => $cpf,
            'email' => null,
            'telefone' => $telefone,
            'tipo' => TipoPessoa::Fisica->value,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $updatedAt ?? now(),
        ]);
    }
}
