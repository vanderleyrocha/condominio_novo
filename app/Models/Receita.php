<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receita extends Model
{
    protected $table = 'receitas';

    protected $fillable = ['data', 'descricao', 'valor', 'contabilizado', 'cobranca_extra_id'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'contabilizado' => 'boolean',
        ];
    }

    public function cobrancaExtra(): BelongsTo
    {
        return $this->belongsTo(CobrancaExtra::class);
    }
}
