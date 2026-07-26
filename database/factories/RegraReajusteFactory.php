<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoIndiceEconomico;
use App\Models\Condominio;
use App\Models\RegraReajuste;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegraReajuste>
 */
class RegraReajusteFactory extends Factory
{
    protected $model = RegraReajuste::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'tipo_indice' => TipoIndiceEconomico::Ipca,
            'periodicidade_meses' => 12,
            'data_base' => fake()->dateTimeBetween('-2 years')->format('Y-m-d'),
        ];
    }
}
