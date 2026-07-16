<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResponsavelPagamento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proprietario extends Model
{
    protected $table = 'proprietarios';

    protected $fillable = [
        'nome', 'cpf', 'telefone',
        'nome_inquilino', 'cpf_inquilino', 'telefone_inquilino',
        'responsavel_pagamento',
    ];

    protected function casts(): array
    {
        return ['responsavel_pagamento' => ResponsavelPagamento::class];
    }

    public function imoveis(): HasMany
    {
        return $this->hasMany(Imovel::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function nomeDoPagador(): string
    {
        return $this->responsavel_pagamento->nomeDoPagador($this);
    }
}
