<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MetodoRateio;
use App\Models\CobrancaExtraordinaria;
use App\Models\Condominio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CobrancaExtraordinaria>
 */
class CobrancaExtraordinariaFactory extends Factory
{
    protected $model = CobrancaExtraordinaria::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'nome' => 'Cobrança '.fake()->words(2, true),
            'valor_total' => fake()->randomFloat(2, 500, 20000),
            'metodo_rateio' => MetodoRateio::Manual,
            'vigencia_inicio' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'vigencia_fim' => null,
            'ativa' => true,
        ];
    }
}
