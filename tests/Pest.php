<?php

use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Datos válidos del formulario de venta, tal como los envía el navegador.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosDeVenta(array $cambios = []): array
{
    return [
        'cliente_nombre' => 'Ana Pérez',
        'cliente_cedula' => '1061234567',
        'cliente_celular' => '3001234567',
        'cliente_correo' => '',
        'articulo' => 'Televisor',
        'marca' => 'Samsung',
        'referencia' => 'UN55',
        'serial' => 'SN-0001',
        'valor_venta' => '1250000',
        'valor_inicial' => '',
        'convenio_id' => Convenio::factory()->create()->id,
        'gasto_administrativo' => '',
        'observaciones' => '',
        ...$cambios,
    ];
}
