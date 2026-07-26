<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Papéis do controle de acesso (03-modelo-dados.md, seção "Controle de acesso"):
 * - Admin: administra o sistema (usuários, configurações, tudo).
 * - Sindico: gestão completa dos condomínios vinculados via condominio_user.
 * - Proprietario: portal do condômino — leitura dos próprios dados
 *   (requer users.pessoa_id).
 */
enum PapelUsuario: string
{
    case Admin = 'admin';
    case Sindico = 'sindico';
    case Proprietario = 'proprietario';

    /**
     * @deprecated Papel do legado. Remapeado para Sindico no cutover (Fase 4);
     *             mantido até lá para os users existentes e as Policies atuais
     *             (regra data_corte_level_one, aposentada no cutover).
     */
    case LevelOne = 'level_one';

    public function rotulo(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Sindico => 'Síndico',
            self::Proprietario => 'Proprietário',
            self::LevelOne => 'Operador',
        };
    }
}
