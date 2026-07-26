<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NaturezaLancamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class LancamentoFinanceiro extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'lancamentos_financeiros';

    protected $fillable = [
        'condominio_id', 'plano_conta_id', 'unidade_id',
        'data_competencia', 'data_lancamento', 'descricao', 'valor',
        'natureza', 'contabilizado', 'origem_type', 'origem_id',
    ];

    protected function casts(): array
    {
        return [
            'data_competencia' => 'date',
            'data_lancamento' => 'date',
            'valor' => 'decimal:2',
            'natureza' => NaturezaLancamento::class,
            'contabilizado' => 'boolean',
        ];
    }

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function origem(): MorphTo
    {
        return $this->morphTo();
    }
}
