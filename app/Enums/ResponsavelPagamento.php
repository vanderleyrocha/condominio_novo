<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Proprietario;

enum ResponsavelPagamento: string
{
    case Proprietario = 'proprietario';
    case Inquilino = 'inquilino';

    /**
     * Substitui o acesso dinâmico $proprietario->$quem_paga do legado (DT-07)
     * por resolução tipada do nome de quem paga.
     */
    public function nomeDoPagador(Proprietario $proprietario): string
    {
        return match ($this) {
            self::Proprietario => $proprietario->nome,
            self::Inquilino => $proprietario->nome_inquilino ?? $proprietario->nome,
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Proprietario => 'Proprietário',
            self::Inquilino => 'Inquilino',
        };
    }
}
