<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FormaPagamento;
use App\Models\PagamentoNovo;
use App\Models\Pessoa;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagamentoNovo>
 */
class PagamentoNovoFactory extends Factory
{
    protected $model = PagamentoNovo::class;

    public function definition(): array
    {
        return [
            'unidade_id' => Unidade::factory(),
            'pessoa_id' => Pessoa::factory(),
            'data_pagamento' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'descricao' => 'Pagamento '.fake()->sentence(3),
            'valor_total' => fake()->randomFloat(2, 100, 1000),
            'forma_pagamento' => FormaPagamento::NaoInformado,
            'estorno_de_id' => null,
        ];
    }

    /**
     * Estorno do pagamento dado — valor negativo (convenção do legado).
     */
    public function estornoDe(PagamentoNovo $original): static
    {
        return $this->state(fn () => [
            'unidade_id' => $original->unidade_id,
            'pessoa_id' => $original->pessoa_id,
            'valor_total' => bcmul((string) $original->valor_total, '-1', 2),
            'descricao' => 'Estorno: '.$original->descricao,
            'estorno_de_id' => $original->id,
        ]);
    }
}
