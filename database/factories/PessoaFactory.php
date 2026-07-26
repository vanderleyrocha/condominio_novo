<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoPessoa;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pessoa>
 */
class PessoaFactory extends Factory
{
    protected $model = Pessoa::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'cpf_cnpj' => fake()->unique()->numerify('###########'),
            'email' => fake()->unique()->safeEmail(),
            'telefone' => fake()->numerify('###########'),
            'tipo' => TipoPessoa::Fisica,
        ];
    }

    public function juridica(): static
    {
        return $this->state(fn () => [
            'nome' => fake()->company(),
            'cpf_cnpj' => fake()->unique()->numerify('##############'),
            'tipo' => TipoPessoa::Juridica,
        ]);
    }

    public function semDocumento(): static
    {
        return $this->state(fn () => ['cpf_cnpj' => null]);
    }
}
