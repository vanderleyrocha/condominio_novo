<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagamentoMensalidade extends Model
{
    protected $table = 'pagamento_mensalidades';

    protected $fillable = ['pagamento_id', 'mensalidade_id', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2'];
    }

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(Pagamento::class);
    }

    public function mensalidade(): BelongsTo
    {
        return $this->belongsTo(Mensalidade::class);
    }
}
