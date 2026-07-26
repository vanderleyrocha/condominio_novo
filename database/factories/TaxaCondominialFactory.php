<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusTaxa;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxaCondominial>
 */
class TaxaCondominialFactory extends Factory
{
    protected $model = TaxaCondominial::class;

    public function definition(): array
    {
        $ano = fake()->numberBetween(2020, 2026);
        $mes = fake()->numberBetween(1, 12);

        return [
            'unidade_id' => Unidade::factory(),
            'competencia_mes' => $mes,
            'competencia_ano' => $ano,
            'vencimento' => sprintf('%d-%02d-10', $ano, $mes),
            'valor_original' => fake()->randomFloat(2, 100, 1000),
            'valor_desconto' => 0,
            'valor_acrescimo' => 0,
            'status' => StatusTaxa::Aberto,
            'contabilizado' => true,
        ];
    }

    public function paga(): static
    {
        return $this->state(fn () => ['status' => StatusTaxa::Pago]);
    }
}
