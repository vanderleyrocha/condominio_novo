<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoIndiceEconomico;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegraReajuste extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'regras_reajuste';

    protected $fillable = [
        'condominio_id', 'tipo_indice', 'periodicidade_meses', 'data_base',
    ];

    protected function casts(): array
    {
        return [
            'tipo_indice' => TipoIndiceEconomico::class,
            'periodicidade_meses' => 'integer',
            'data_base' => 'date',
        ];
    }

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }
}
