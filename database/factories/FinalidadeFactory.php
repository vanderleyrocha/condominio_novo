<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Condominio;
use App\Models\Finalidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finalidade>
 */
class FinalidadeFactory extends Factory
{
    protected $model = Finalidade::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'nome' => 'Finalidade '.fake()->unique()->words(2, true),
            'descricao' => null,
            'meta_valor' => null,
            'restrita' => false,
            'vigencia_inicio' => null,
            'vigencia_fim' => null,
            'ativa' => true,
        ];
    }

    public function restrita(): static
    {
        return $this->state(fn (): array => ['restrita' => true]);
    }

    public function custeioOrdinario(): static
    {
        return $this->state(fn (): array => [
            'nome' => 'Custeio ordinário',
            'descricao' => 'Despesas correntes de manutenção e administração do condomínio.',
        ]);
    }
}
