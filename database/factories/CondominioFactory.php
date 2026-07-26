<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Condominio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Condominio>
 */
class CondominioFactory extends Factory
{
    protected $model = Condominio::class;

    public function definition(): array
    {
        return [
            'nome' => 'Condomínio '.fake()->unique()->company(),
            'cnpj' => fake()->unique()->numerify('##############'),
            'endereco' => fake()->streetAddress(),
            'cidade' => fake()->city(),
            'uf' => fake()->randomElement(['SP', 'RJ', 'MG', 'BA', 'PE']),
            'cep' => fake()->numerify('########'),
        ];
    }
}
