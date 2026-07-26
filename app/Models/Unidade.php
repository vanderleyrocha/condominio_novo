<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Unidade extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'unidades';

    protected $fillable = [
        'condominio_id', 'bloco_id', 'identificacao',
        'fracao_ideal', 'area', 'vagas_garagem',
    ];

    protected function casts(): array
    {
        return [
            'fracao_ideal' => 'decimal:6',
            'area' => 'decimal:2',
            'vagas_garagem' => 'integer',
        ];
    }

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    public function bloco(): BelongsTo
    {
        return $this->belongsTo(Bloco::class);
    }

    public function pessoas(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'unidade_pessoa')
            ->using(UnidadePessoa::class)
            ->withPivot(['papel', 'responsavel_financeiro', 'data_inicio', 'data_fim'])
            ->withTimestamps();
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(UnidadePessoa::class);
    }

    public function taxasCondominiais(): HasMany
    {
        return $this->hasMany(TaxaCondominial::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function lancamentosFinanceiros(): HasMany
    {
        return $this->hasMany(LancamentoFinanceiro::class);
    }
}
