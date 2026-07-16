<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pagamento extends Model
{
    protected $table = 'pagamentos';

    protected $fillable = [
        'proprietario_id', 'imovel_id', 'pagamento_origem_id',
        'data', 'descricao', 'valor', 'estornado',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'estornado' => 'boolean', // corrige DT-14 do legado
        ];
    }

    public function proprietario(): BelongsTo
    {
        return $this->belongsTo(Proprietario::class);
    }

    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    // Autorrelação de estorno navegável (corrige DT-15 do legado)
    public function pagamentoOrigem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pagamento_origem_id');
    }

    public function estornos(): HasMany
    {
        return $this->hasMany(self::class, 'pagamento_origem_id');
    }

    public function mensalidades(): BelongsToMany
    {
        return $this->belongsToMany(Mensalidade::class, 'pagamento_mensalidades')
            ->withPivot('valor')
            ->withTimestamps();
    }

    public function isEstorno(): bool
    {
        return $this->pagamento_origem_id !== null;
    }
}
