<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoPlanoConta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanoConta extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'planos_contas';

    protected $fillable = [
        'condominio_id', 'codigo', 'descricao', 'tipo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoPlanoConta::class,
        ];
    }

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    public function lancamentosFinanceiros(): HasMany
    {
        return $this->hasMany(LancamentoFinanceiro::class);
    }
}
