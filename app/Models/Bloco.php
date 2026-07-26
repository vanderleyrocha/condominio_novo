<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bloco extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'blocos';

    protected $fillable = ['condominio_id', 'nome'];

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }
}
