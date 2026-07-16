<?php

declare(strict_types=1);

namespace App\Enums;

enum MetodoCorrecao: string
{
    // Soma simples é o método vigente do legado (paridade — RN-21);
    // composta é a alternativa configurável decidida em P7.
    case SomaSimples = 'soma_simples';
    case Composta = 'composta';

    public function rotulo(): string
    {
        return match ($this) {
            self::SomaSimples => 'Soma simples dos índices',
            self::Composta => 'Correção composta mês a mês',
        };
    }
}
