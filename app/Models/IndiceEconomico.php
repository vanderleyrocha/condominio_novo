<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoIndiceEconomico;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndiceEconomico extends Model
{
    use HasFactory;

    protected $table = 'indices_economicos';

    protected $fillable = ['tipo', 'ano', 'mes', 'indice'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoIndiceEconomico::class,
            'ano' => 'integer',
            'mes' => 'integer',
            'indice' => 'decimal:4',
        ];
    }
}
