<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusTaxa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class TaxaCondominial extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'taxas_condominiais';

    protected $fillable = [
        'unidade_id', 'competencia_mes', 'competencia_ano', 'vencimento',
        'valor_original', 'valor_desconto', 'valor_acrescimo',
        'status', 'contabilizado',
    ];

    protected function casts(): array
    {
        return [
            'vencimento' => 'date',
            'valor_original' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'valor_acrescimo' => 'decimal:2',
            // Cache de leitura — recalcular apenas via StatusTaxaService
            'status' => StatusTaxa::class,
            'contabilizado' => 'boolean',
        ];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function pagamentos(): BelongsToMany
    {
        return $this->belongsToMany(PagamentoNovo::class, 'pagamento_taxa', 'taxa_condominial_id', 'pagamento_id')
            ->withPivot('valor_aplicado')
            ->withTimestamps();
    }

    public function pagamentoTaxas(): HasMany
    {
        return $this->hasMany(PagamentoTaxa::class);
    }

    public function cobrancasExtraordinarias(): BelongsToMany
    {
        return $this->belongsToMany(CobrancaExtraordinaria::class, 'cobranca_extraordinaria_taxa')
            ->withPivot('valor')
            ->withTimestamps();
    }

    /**
     * devido = valor_original + valor_acrescimo - valor_desconto (BCMath,
     * definição única de 02-mapeamento-de-para.md §3).
     */
    public function valorDevido(): string
    {
        return bcsub(
            bcadd((string) $this->valor_original, (string) $this->valor_acrescimo, 2),
            (string) $this->valor_desconto,
            2
        );
    }

    public function vencida(): bool
    {
        return $this->status !== StatusTaxa::Pago
            && $this->vencimento !== null
            && $this->vencimento->isPast();
    }
}
