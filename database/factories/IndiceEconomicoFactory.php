<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoIndiceEconomico;
use App\Models\IndiceEconomico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndiceEconomico>
 */
class IndiceEconomicoFactory extends Factory
{
    protected $model = IndiceEconomico::class;

    public function definition(): array
    {
        return [
            'tipo' => TipoIndiceEconomico::Ipca,
            'ano' => fake()->unique()->numberBetween(2000, 2100),
            'mes' => fake()->numberBetween(1, 12),
            'indice' => fake()->randomFloat(4, -0.5, 1.5),
        ];
    }
}
