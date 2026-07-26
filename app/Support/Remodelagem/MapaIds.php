<?php

declare(strict_types=1);

namespace App\Support\Remodelagem;

use Illuminate\Support\Facades\DB;

/**
 * Acesso ao mapa old_id -> new_id da remodelagem (`migration_id_map`,
 * 04-plano-migracao.md). Para `pessoa` o mapa é N:1 — vários ids antigos
 * podem apontar para a mesma pessoa nova (deduplicação por CPF).
 */
final class MapaIds
{
    public static function limpar(string ...$entidades): void
    {
        if ($entidades !== []) {
            DB::table('migration_id_map')->whereIn('entidade', $entidades)->delete();
        }
    }

    /**
     * @param  list<array{id_antigo: int, id_novo: int}>  $pares
     */
    public static function registrarLote(string $entidade, array $pares): void
    {
        $agora = now();

        foreach (array_chunk($pares, 500) as $chunk) {
            DB::table('migration_id_map')->insert(array_map(
                fn (array $par): array => [
                    'entidade' => $entidade,
                    'id_antigo' => $par['id_antigo'],
                    'id_novo' => $par['id_novo'],
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ],
                $chunk,
            ));
        }
    }

    public static function registrar(string $entidade, int $idAntigo, int $idNovo): void
    {
        self::registrarLote($entidade, [['id_antigo' => $idAntigo, 'id_novo' => $idNovo]]);
    }

    /**
     * Mapa completo da entidade em memória: [id_antigo => id_novo].
     *
     * @return array<int, int>
     */
    public static function carregar(string $entidade): array
    {
        return DB::table('migration_id_map')
            ->where('entidade', $entidade)
            ->pluck('id_novo', 'id_antigo')
            ->all();
    }
}
