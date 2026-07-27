<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetodoRateio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CobrancaExtraordinaria extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cobrancas_extraordinarias';

    protected $fillable = [
        'condominio_id', 'nome', 'finalidade_id', 'valor_total', 'valor_por_unidade',
        'metodo_rateio', 'vigencia_inicio', 'vigencia_fim', 'ativa',
    ];

    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'valor_por_unidade' => 'decimal:2',
            'metodo_rateio' => MetodoRateio::class,
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'ativa' => 'boolean',
        ];
    }

    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    public function finalidade(): BelongsTo
    {
        return $this->belongsTo(Finalidade::class);
    }

    /**
     * Itens gerados por esta campanha nas taxas dentro da vigência
     * (05-plano-composicao-taxas.md D-04) — substitui o pivô
     * cobranca_extraordinaria_taxa, descontinuado na Etapa 6.
     */
    public function itensTaxa(): MorphMany
    {
        return $this->morphMany(ItemTaxa::class, 'origem');
    }

    /**
     * @deprecated Substituído por itensTaxa(); removido na Etapa 6 do plano.
     */
    public function taxasCondominiais(): BelongsToMany
    {
        return $this->belongsToMany(TaxaCondominial::class, 'cobranca_extraordinaria_taxa')
            ->withPivot('valor')
            ->withTimestamps();
    }

    /**
     * A campanha cobre a competência informada? (vigência aberta = permanente)
     */
    public function cobreCompetencia(int $ano, int $mes): bool
    {
        $ultimoDia = sprintf('%d-%02d-%02d', $ano, $mes, (int) date('t', (int) mktime(0, 0, 0, $mes, 1, $ano)));
        $primeiroDia = sprintf('%d-%02d-01', $ano, $mes);

        if (! $this->ativa) {
            return false;
        }

        // Sobreposição de intervalos: a campanha cobre a competência se sua
        // vigência intersecta qualquer dia do mês
        if ($this->vigencia_inicio !== null && $this->vigencia_inicio->toDateString() > $ultimoDia) {
            return false;
        }

        return $this->vigencia_fim === null || $this->vigencia_fim->toDateString() >= $primeiroDia;
    }
}
