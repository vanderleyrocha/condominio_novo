<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ItemTaxa;
use App\Models\PlanoConta;
use App\Models\TaxaCondominial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemTaxa>
 */
class ItemTaxaFactory extends Factory
{
    protected $model = ItemTaxa::class;

    public function definition(): array
    {
        return [
            'taxa_condominial_id' => TaxaCondominial::factory(),
            'plano_conta_id' => PlanoConta::factory(),
            'finalidade_id' => null,
            'descricao' => 'Taxa condominial',
            'valor' => '100.00',
            'ordem' => 0,
        ];
    }

    public function contribuicao(string $descricao, string $valor, int $ordem = 1): static
    {
        return $this->state(fn (): array => [
            'descricao' => $descricao,
            'valor' => $valor,
            'ordem' => $ordem,
        ]);
    }
}
