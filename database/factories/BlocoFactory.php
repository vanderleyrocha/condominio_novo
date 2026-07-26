<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bloco;
use App\Models\Condominio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bloco>
 */
class BlocoFactory extends Factory
{
    protected $model = Bloco::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'nome' => 'Bloco '.fake()->unique()->randomLetter(),
        ];
    }
}
