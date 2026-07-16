<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Despesa extends Model
{
    protected $table = 'despesas';

    protected $fillable = ['despesa_tipo_id', 'data', 'descricao', 'valor', 'contabilizado'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'contabilizado' => 'boolean',
        ];
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(DespesaTipo::class, 'despesa_tipo_id');
    }

    // mes/ano do legado agora são derivados (target_data_model)
    public function getMesAttribute(): int
    {
        return (int) $this->data->format('n');
    }

    public function getAnoAttribute(): int
    {
        return (int) $this->data->format('Y');
    }
}
