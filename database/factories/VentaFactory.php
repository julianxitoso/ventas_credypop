<?php

namespace Database\Factories;

use App\Models\Convenio;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaProducto;
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
            'cliente_telefono' => fake()->numerify('602#######'),
            'cliente_celular_2' => null,
            'cliente_correo' => fake()->safeEmail(),
            'cliente_direccion' => fake()->streetAddress(),
            'cliente_ciudad' => 'Popayán',
            'valor_venta' => 1_250_000,
            'valor_inicial' => 0,
            'observaciones' => null,
        ];
    }

    /**
     * Si la prueba no definió productos ni convenios, la venta queda con
     * un producto por el total y un convenio que cubre el total menos la inicial.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Venta $venta): void {
            if (! $venta->productos()->exists()) {
                VentaProducto::factory()->for($venta)->create(['valor' => $venta->valor_venta]);
            }

            if (! $venta->convenios()->exists()) {
                $venta->convenios()->attach(Convenio::factory()->create(), [
                    'valor' => max(0, $venta->valor_venta - $venta->valor_inicial),
                    'gasto_administrativo' => null,
                ]);
            }
        });
    }

    /**
     * La venta queda con el convenio indicado (por defecto cubre el total menos la inicial).
     */
    public function conConvenio(Convenio $convenio, ?int $valor = null, ?int $gastoAdministrativo = null): static
    {
        // hasAttached se ejecuta antes que configure(), así este no agrega otro convenio.
        return $this->hasAttached($convenio, ['valor' => $valor ?? 0, 'gasto_administrativo' => $gastoAdministrativo], 'convenios')
            ->afterCreating(function (Venta $venta) use ($convenio, $valor): void {
                if ($valor === null) {
                    $venta->convenios()->updateExistingPivot($convenio->id, [
                        'valor' => max(0, $venta->valor_venta - $venta->valor_inicial),
                    ]);
                }
            });
    }
}
