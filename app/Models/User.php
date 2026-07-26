<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PapelUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'papel', 'foto_perfil', 'pessoa_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'papel' => PapelUsuario::class,
        ];
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(Access::class);
    }

    // Vínculos do schema novo (docs/migration/03-modelo-dados.md)
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function condominios(): BelongsToMany
    {
        return $this->belongsToMany(Condominio::class, 'condominio_user')->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->papel === PapelUsuario::Admin;
    }

    public function isSindico(): bool
    {
        return $this->papel === PapelUsuario::Sindico;
    }

    public function isProprietario(): bool
    {
        return $this->papel === PapelUsuario::Proprietario;
    }

    /**
     * @deprecated Papel do legado — remapeado para Sindico no cutover (Fase 4).
     */
    public function isLevelOne(): bool
    {
        return $this->papel === PapelUsuario::LevelOne;
    }
}
