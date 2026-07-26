<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Condominio;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unidade>
 */
class UnidadeFactory extends Factory
{
    protected $model = Unidade::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'bloco_id' => null,
            'identificacao' => 'Casa '.fake()->unique()->numberBetween(1, 9999),
            'fracao_ideal' => null,
            'area' => fake()->randomFloat(2, 40, 300),
            'vagas_garagem' => fake()->numberBetween(0, 3),
        ];
    }
}
