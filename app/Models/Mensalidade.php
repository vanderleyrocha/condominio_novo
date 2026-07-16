<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusMensalidade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Mensalidade extends Model
{
    protected $table = 'mensalidades';

    protected $fillable = [
        'imovel_id', 'mes', 'ano', 'vencimento',
        'valor', 'desconto', 'acrescimo', 'valor_pago',
        'pago_em', 'contabilizado',
    ];

    protected function casts(): array
    {
        return [
            'vencimento' => 'date',
            'pago_em' => 'date',
            'contabilizado' => 'boolean',
            'valor' => 'decimal:2',
            'desconto' => 'decimal:2',
            'acrescimo' => 'decimal:2',
            'valor_pago' => 'decimal:2',
        ];
    }

    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    public function pagamentos(): BelongsToMany
    {
        return $this->belongsToMany(Pagamento::class, 'pagamento_mensalidades')
            ->withPivot('valor')
            ->withTimestamps();
    }

    /**
     * "Em aberto" — semântica exata do legado (domain.md §1):
     * COALESCE(valor_pago,0) < COALESCE(valor,0) - COALESCE(desconto,0) E vencimento < hoje.
     */
    public function scopeEmAberto(Builder $query): Builder
    {
        return $query
            ->whereRaw('COALESCE(valor_pago, 0) < (COALESCE(valor, 0) - COALESCE(desconto, 0))')
            ->where('vencimento', '<', now()->toDateString());
    }

    public function getValorLiquidoAttribute(): string
    {
        return number_format((float) $this->valor - (float) $this->desconto, 2, '.', '');
    }

    /**
     * devido = valor + acrescimo - desconto - valor_pago (RN-13)
     */
    public function valorDevido(): string
    {
        $devido = (float) $this->valor + (float) $this->acrescimo
            - (float) $this->desconto - (float) $this->valor_pago;

        return number_format(max($devido, 0), 2, '.', '');
    }

    public function status(): StatusMensalidade
    {
        $liquido = (float) $this->valor - (float) $this->desconto;
        $pago = (float) $this->valor_pago;

        if ($pago >= $liquido && $pago > 0) {
            return StatusMensalidade::Paga;
        }
        if ($pago > 0) {
            return StatusMensalidade::PagaParcial;
        }
        if ($this->vencimento !== null && $this->vencimento->isPast()) {
            return StatusMensalidade::Vencida;
        }

        return StatusMensalidade::EmAberto;
    }
}
