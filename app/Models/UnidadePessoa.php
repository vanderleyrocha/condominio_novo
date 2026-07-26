<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PapelVinculo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Vínculo pessoa↔unidade com papel e vigência (03-modelo-dados.md).
 * Regra de aplicação: no máximo 1 vínculo vigente com responsavel_financeiro=true
 * por unidade — validar no Action de salvamento, não aqui.
 */
class UnidadePessoa extends Pivot
{
    use HasFactory;

    public $incrementing = true;

    protected $table = 'unidade_pessoa';

    protected $fillable = [
        'unidade_id', 'pessoa_id', 'papel',
        'responsavel_financeiro', 'data_inicio', 'data_fim',
    ];

    protected function casts(): array
    {
        return [
            'papel' => PapelVinculo::class,
            'responsavel_financeiro' => 'boolean',
            'data_inicio' => 'date',
            'data_fim' => 'date',
        ];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function vigente(): bool
    {
        return $this->data_fim === null || $this->data_fim->isFuture();
    }
}
