<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PapelVinculo;
use App\Models\Pessoa;
use App\Models\Unidade;
use App\Models\UnidadePessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnidadePessoa>
 */
class UnidadePessoaFactory extends Factory
{
    protected $model = UnidadePessoa::class;

    public function definition(): array
    {
        return [
            'unidade_id' => Unidade::factory(),
            'pessoa_id' => Pessoa::factory(),
            'papel' => PapelVinculo::Proprietario,
            'responsavel_financeiro' => true,
            'data_inicio' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'data_fim' => null,
        ];
    }

    public function inquilino(): static
    {
        return $this->state(fn () => [
            'papel' => PapelVinculo::Inquilino,
            'responsavel_financeiro' => false,
        ]);
    }

    public function encerrado(): static
    {
        return $this->state(fn () => [
            'data_fim' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
        ]);
    }
}
