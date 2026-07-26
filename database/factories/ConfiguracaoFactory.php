<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Condominio;
use App\Models\Configuracao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Configuracao>
 */
class ConfiguracaoFactory extends Factory
{
    protected $model = Configuracao::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'chave' => fake()->unique()->slug(3),
            'valor' => fake()->word(),
            'tipo_dado' => 'string',
        ];
    }

    public function global(): static
    {
        return $this->state(fn () => ['condominio_id' => null]);
    }
}
