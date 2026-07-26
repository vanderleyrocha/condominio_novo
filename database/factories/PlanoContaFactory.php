<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoPlanoConta;
use App\Models\Condominio;
use App\Models\PlanoConta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanoConta>
 */
class PlanoContaFactory extends Factory
{
    protected $model = PlanoConta::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'codigo' => fake()->unique()->numerify('#.##'),
            'descricao' => fake()->words(3, true),
            'tipo' => fake()->randomElement(TipoPlanoConta::cases()),
        ];
    }

    public function receita(): static
    {
        return $this->state(fn () => ['tipo' => TipoPlanoConta::Receita]);
    }

    public function despesa(): static
    {
        return $this->state(fn () => ['tipo' => TipoPlanoConta::Despesa]);
    }
}
