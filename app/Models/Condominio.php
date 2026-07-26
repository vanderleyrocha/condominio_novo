<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Condominio extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'condominios';

    protected $fillable = [
        'nome', 'cnpj', 'endereco', 'cidade', 'uf', 'cep',
    ];

    public function blocos(): HasMany
    {
        return $this->hasMany(Bloco::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }

    public function planosContas(): HasMany
    {
        return $this->hasMany(PlanoConta::class);
    }

    public function lancamentosFinanceiros(): HasMany
    {
        return $this->hasMany(LancamentoFinanceiro::class);
    }

    public function cobrancasExtraordinarias(): HasMany
    {
        return $this->hasMany(CobrancaExtraordinaria::class);
    }

    public function regrasReajuste(): HasMany
    {
        return $this->hasMany(RegraReajuste::class);
    }

    public function configuracoes(): HasMany
    {
        return $this->hasMany(Configuracao::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'condominio_user')->withTimestamps();
    }
}
