<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Uma linha cobrada dentro da mensalidade (docs/migration/05-plano-composicao-taxas.md §3.2).
 * A taxa condominial é o contêiner; a soma dos itens é o valor_original da taxa
 * (invariante da §3.4, mantida por App\Services\ComposicaoTaxaService).
 *
 * `ordem` define a ordem de quitação em cascata (D-03): 0 = taxa ordinária,
 * contribuições depois.
 */
class ItemTaxa extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'itens_taxa_condominial';

    protected $fillable = [
        'taxa_condominial_id', 'plano_conta_id', 'finalidade_id',
        'descricao', 'valor', 'ordem', 'origem_type', 'origem_id',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'ordem' => 'integer',
        ];
    }

    public function taxaCondominial(): BelongsTo
    {
        return $this->belongsTo(TaxaCondominial::class);
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class);
    }

    public function finalidade(): BelongsTo
    {
        return $this->belongsTo(Finalidade::class);
    }

    public function origem(): MorphTo
    {
        return $this->morphTo();
    }
}
