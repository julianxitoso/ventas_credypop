<?php

namespace Database\Factories;

use App\Models\Venta;
use App\Models\VentaProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VentaProducto>
 */
class VentaProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venta_id' => Venta::factory(),
            'articulo' => fake()->randomElement(['Televisor', 'Nevera', 'Lavadora', 'Celular']),
            'marca' => fake()->company(),
            'referencia' => fake()->bothify('REF-####'),
            'serial' => fake()->unique()->bothify('SN-########'),
            'valor' => 1_250_000,
        ];
    }
}
