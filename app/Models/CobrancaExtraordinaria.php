<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetodoRateio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CobrancaExtraordinaria extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cobrancas_extraordinarias';

    protected $fillable = [
        'condominio_id', 'nome', 'valor_total', 'metodo_rateio',
        'vigencia_inicio', 'vigencia_fim', 'ativa',
    ];

    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'metodo_rateio' => MetodoRateio::class,
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'ativa' => 'boolean',
        ];
    }

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    public function taxasCondominiais(): BelongsToMany
    {
        return $this->belongsToMany(TaxaCondominial::class, 'cobranca_extraordinaria_taxa')
            ->withPivot('valor')
            ->withTimestamps();
    }
}
