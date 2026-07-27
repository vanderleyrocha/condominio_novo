<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Destinação (afetação) de receita — "para que serve o dinheiro que entra".
 * Transversal a todas as fontes de receita: itens de taxa condominial,
 * lançamentos financeiros e cobranças extraordinárias
 * (docs/migration/05-plano-composicao-taxas.md §3.1).
 */
class Finalidade extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'finalidades';

    protected $fillable = [
        'condominio_id', 'nome', 'descricao', 'meta_valor', 'restrita',
        'vigencia_inicio', 'vigencia_fim', 'ativa',
    ];

    protected function casts(): array
    {
        return [
            'meta_valor' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'ativa' => 'boolean',
            'restrita' => 'boolean',
        ];
    }

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    public function itensTaxa(): HasMany
    {
        return $this->hasMany(ItemTaxa::class);
    }

    public function lancamentosFinanceiros(): HasMany
    {
        return $this->hasMany(LancamentoFinanceiro::class);
    }

    public function cobrancasExtraordinarias(): HasMany
    {
        return $this->hasMany(CobrancaExtraordinaria::class);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativa', true);
    }

    /**
     * Recursos carimbados — o saldo delas não está disponível para o custeio
     * ordinário e sai destacado no Resumo financeiro.
     */
    public function scopeRestritas(Builder $query): Builder
    {
        return $query->where('restrita', true);
    }

    /**
     * Vigente na data informada (vigência aberta = permanente).
     */
    public function scopeVigenteEm(Builder $query, string $data): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('vigencia_inicio')->orWhere('vigencia_inicio', '<=', $data))
            ->where(fn (Builder $q) => $q->whereNull('vigencia_fim')->orWhere('vigencia_fim', '>=', $data));
    }
}
