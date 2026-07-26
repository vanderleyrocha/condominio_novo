<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FormaPagamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Pagamento do schema novo. Classe e tabela levam o sufixo "Novo" apenas
 * durante a coexistência com o schema antigo (`pagamentos` legado); no cutover
 * (Fase 4) a tabela é renomeada para `pagamentos` e a classe para `Pagamento`.
 *
 * Convenção de sinal: estornos têm valor_total NEGATIVO (legado preservado);
 * `estorno_de_id` carrega a semântica.
 */
class PagamentoNovo extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pagamentos_novo';

    protected $fillable = [
        'unidade_id', 'pessoa_id', 'data_pagamento', 'descricao',
        'valor_total', 'forma_pagamento', 'estorno_de_id',
    ];

    protected function casts(): array
    {
        return [
            'data_pagamento' => 'date',
            'valor_total' => 'decimal:2',
            'forma_pagamento' => FormaPagamento::class,
        ];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function estornoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'estorno_de_id');
    }

    public function estornos(): HasMany
    {
        return $this->hasMany(self::class, 'estorno_de_id');
    }

    public function taxasCondominiais(): BelongsToMany
    {
        return $this->belongsToMany(TaxaCondominial::class, 'pagamento_taxa', 'pagamento_id', 'taxa_condominial_id')
            ->withPivot('valor_aplicado')
            ->withTimestamps();
    }

    public function pagamentoTaxas(): HasMany
    {
        return $this->hasMany(PagamentoTaxa::class, 'pagamento_id');
    }

    public function isEstorno(): bool
    {
        return $this->estorno_de_id !== null;
    }
}
