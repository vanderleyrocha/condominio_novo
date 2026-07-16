<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ipca extends Model
{
    protected $table = 'ipcas';

    protected $fillable = ['ano', 'mes', 'indice'];

    protected function casts(): array
    {
        return ['indice' => 'decimal:4'];
    }
}
