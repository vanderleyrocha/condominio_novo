<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PagamentoTaxa extends Pivot
{
    use HasFactory;

    public $incrementing = true;

    protected $table = 'pagamento_taxa';

    protected $fillable = [
        'pagamento_id', 'taxa_condominial_id', 'valor_aplicado',
    ];

    protected function casts(): array
    {
        return [
            'valor_aplicado' => 'decimal:2', // negativo em estornos
        ];
    }

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(PagamentoNovo::class, 'pagamento_id');
    }

    public function taxaCondominial(): BelongsTo
    {
        return $this->belongsTo(TaxaCondominial::class);
    }
}
