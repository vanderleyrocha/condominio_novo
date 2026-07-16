<?php

declare(strict_types=1);

namespace App\Enums;

enum PapelUsuario: string
{
    case Admin = 'admin';
    case LevelOne = 'level_one';

    public function rotulo(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::LevelOne => 'Operador',
        };
    }
}
