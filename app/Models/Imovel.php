<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Imovel extends Model
{
    protected $table = 'imoveis';

    protected $fillable = ['proprietario_id', 'nome'];

    public function proprietario(): BelongsTo
    {
        return $this->belongsTo(Proprietario::class);
    }

    public function mensalidades(): HasMany
    {
        return $this->hasMany(Mensalidade::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }
}
