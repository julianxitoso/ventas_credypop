<?php

namespace Database\Factories;

use App\Models\Convenio;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venta>
 */
class VentaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asesor_id' => User::factory(),
            'cliente_nombre' => fake()->name(),
            'cliente_cedula' => fake()->numerify('##########'),
            'cliente_celular' => fake()->numerify('3#########'),
            'cliente_correo' => null,
            'articulo' => fake()->randomElement(['Televisor', 'Nevera', 'Lavadora', 'Celular']),
            'marca' => fake()->company(),
            'referencia' => fake()->bothify('REF-####'),
            'serial' => fake()->unique()->bothify('SN-########'),
            'valor_venta' => 1_250_000,
            'valor_inicial' => 0,
            'convenio_id' => Convenio::factory(),
            'gasto_administrativo' => null,
            'observaciones' => null,
        ];
    }
}
