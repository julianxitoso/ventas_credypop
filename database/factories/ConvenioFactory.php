<?php

namespace Database\Factories;

use App\Models\Convenio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Convenio>
 */
class ConvenioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'requiere_gasto' => false,
            'activo' => true,
        ];
    }

    /**
     * Indicate that the convenio requires the administrative expense.
     */
    public function conGasto(): static
    {
        return $this->state(fn (array $attributes) => [
            'requiere_gasto' => true,
        ]);
    }

    /**
     * Indicate that the convenio has been deactivated.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
