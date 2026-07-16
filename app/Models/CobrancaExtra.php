<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CobrancaExtra extends Model
{
    protected $table = 'cobrancas_extras';

    protected $fillable = ['nome', 'valor', 'vigencia_inicio', 'vigencia_fim', 'ativa'];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'ativa' => 'boolean',
        ];
    }

    public function mensalidades(): BelongsToMany
    {
        return $this->belongsToMany(Mensalidade::class, 'cobranca_extra_mensalidade')
            ->withPivot('valor')
            ->withTimestamps();
    }

    public function receitas(): HasMany
    {
        return $this->hasMany(Receita::class);
    }
}
