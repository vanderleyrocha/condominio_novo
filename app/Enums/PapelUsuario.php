<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Papéis do controle de acesso (03-modelo-dados.md, seção "Controle de acesso"):
 * - Admin: administra o sistema (usuários, configurações, tudo).
 * - Sindico: gestão completa dos condomínios vinculados via condominio_user.
 * - Proprietario: portal do condômino — leitura dos próprios dados
 *   (requer users.pessoa_id).
 *
 * O papel legado 'level_one' foi remapeado para 'sindico' no cutover
 * (migrar:remapear-papeis) e removido na Fase 5.
 */
enum PapelUsuario: string
{
    case Admin = 'admin';
    case Sindico = 'sindico';
    case Proprietario = 'proprietario';

    public function rotulo(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Sindico => 'Síndico',
            self::Proprietario => 'Proprietário',
        };
    }
}
