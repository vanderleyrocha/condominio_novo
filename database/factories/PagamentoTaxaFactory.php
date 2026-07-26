<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pagamento;
use App\Models\PagamentoTaxa;
use App\Models\TaxaCondominial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagamentoTaxa>
 */
class PagamentoTaxaFactory extends Factory
{
    protected $model = PagamentoTaxa::class;

    public function definition(): array
    {
        return [
            'pagamento_id' => Pagamento::factory(),
            'taxa_condominial_id' => TaxaCondominial::factory(),
            'valor_aplicado' => fake()->randomFloat(2, 50, 500),
        ];
    }
}
