<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NaturezaLancamento;
use App\Enums\TipoPlanoConta;
use App\Models\Condominio;
use App\Models\LancamentoFinanceiro;
use App\Models\PlanoConta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LancamentoFinanceiro>
 */
class LancamentoFinanceiroFactory extends Factory
{
    protected $model = LancamentoFinanceiro::class;

    public function definition(): array
    {
        $data = fake()->dateTimeBetween('-1 year')->format('Y-m-d');

        return [
            'condominio_id' => Condominio::factory(),
            'plano_conta_id' => PlanoConta::factory()->despesa(),
            'unidade_id' => null,
            'data_competencia' => $data,
            'data_lancamento' => $data,
            'descricao' => fake()->sentence(4),
            'valor' => fake()->randomFloat(2, 50, 5000),
            'natureza' => NaturezaLancamento::Despesa,
            'contabilizado' => true,
            'origem_type' => null,
            'origem_id' => null,
        ];
    }

    public function receita(): static
    {
        return $this->state(fn () => [
            'plano_conta_id' => PlanoConta::factory()->state(['tipo' => TipoPlanoConta::Receita]),
            'natureza' => NaturezaLancamento::Receita,
        ]);
    }
}
