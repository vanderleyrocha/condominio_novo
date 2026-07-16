<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DespesaTipo extends Model
{
    protected $table = 'despesa_tipos';

    protected $fillable = ['descricao'];

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class);
    }
}
