<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoPessoa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Pessoa extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pessoas';

    protected $fillable = [
        'nome', 'cpf_cnpj', 'email', 'telefone', 'tipo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoPessoa::class,
        ];
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'unidade_pessoa')
            ->using(UnidadePessoa::class)
            ->withPivot(['papel', 'responsavel_financeiro', 'data_inicio', 'data_fim'])
            ->withTimestamps();
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(UnidadePessoa::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(PagamentoNovo::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
