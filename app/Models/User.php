<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PapelUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'papel', 'foto_perfil'];

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

    public function isAdmin(): bool
    {
        return $this->papel === PapelUsuario::Admin;
    }

    public function isLevelOne(): bool
    {
        return $this->papel === PapelUsuario::LevelOne;
    }
}
