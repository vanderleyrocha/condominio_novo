<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Configuracao extends Model
{
    use HasFactory;

    protected $table = 'configuracoes';

    protected $fillable = [
        'condominio_id', 'chave', 'valor', 'tipo_dado',
    ];

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    /**
     * Valor tipado conforme tipo_dado (string|int|decimal|bool|json).
     */
    public function valorTipado(): mixed
    {
        return match ($this->tipo_dado) {
            'int' => (int) $this->valor,
            'decimal' => $this->valor, // manter string — aritmética via BCMath
            'bool' => filter_var($this->valor, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->valor, true),
            default => $this->valor,
        };
    }
}
