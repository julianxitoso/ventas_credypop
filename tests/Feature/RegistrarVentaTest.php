<?php

use App\Enums\EstadoVenta;
use App\Enums\Rol;
use App\Models\Convenio;
use App\Models\User;
use App\Models\Venta;
use Inertia\Testing\AssertableInertia as Assert;

test('asesores register a pending sale in their own name', function () {
    $asesor = User::factory()->create();
    $otroAsesor = User::factory()->create();

    $response = $this->actingAs($asesor)
        ->from(route('ventas.create'))
        ->post(route('ventas.store'), datosDeVenta(['asesor_id' => $otroAsesor->id]));

    $venta = Venta::sole();

    $response->assertRedirect(route('ventas.create'))
        ->assertInertiaFlash('ventaRegistrada', $venta->consecutivo);

    expect($venta->asesor_id)->toBe($asesor->id)
        ->and($venta->refresh()->estado)->toBe(EstadoVenta::Pendiente)
        ->and($venta->valor_venta)->toBe(1_250_000)
        ->and($venta->valor_inicial)->toBe(0)
        ->and($venta->gasto_administrativo)->toBeNull();
});

test('formatted peso values are stored as whole pesos', function () {
    $asesor = User::factory()->create();

    $this->actingAs($asesor)->post(route('ventas.store'), datosDeVenta([
        'valor_venta' => '$1.250.000',
        'valor_inicial' => '$250.000',
    ]))->assertSessionHasNoErrors();

    expect(Venta::sole())
        ->valor_venta->toBe(1_250_000)
        ->valor_inicial->toBe(250_000);
});

test('a convenio that requires it demands the administrative expense', function () {
    $asesor = User::factory()->create();
    $krediya = Convenio::factory()->conGasto()->create();

    $this->actingAs($asesor)
        ->post(route('ventas.store'), datosDeVenta(['convenio_id' => $krediya->id]))
        ->assertSessionHasErrors(['gasto_administrativo' => 'Este convenio requiere el gasto administrativo.']);

    $this->actingAs($asesor)
        ->post(route('ventas.store'), datosDeVenta([
            'convenio_id' => $krediya->id,
            'gasto_administrativo' => '45000',
        ]))
        ->assertSessionHasNoErrors();

    expect(Venta::sole()->gasto_administrativo)->toBe(45_000);
});

test('the administrative expense is discarded when the convenio does not require it', function () {
    $asesor = User::factory()->create();

    $this->actingAs($asesor)
        ->post(route('ventas.store'), datosDeVenta(['gasto_administrativo' => '45000']))
        ->assertSessionHasNoErrors();

    expect(Venta::sole()->gasto_administrativo)->toBeNull();
});

test('the sale is rejected when the data is invalid', function (array $cambios, string $campo) {
    $asesor = User::factory()->create();

    $this->actingAs($asesor)
        ->post(route('ventas.store'), datosDeVenta($cambios))
        ->assertSessionHasErrors($campo);

    expect(Venta::count())->toBe(0);
})->with([
    'inicial mayor que la venta' => [['valor_venta' => '1000000', 'valor_inicial' => '1000001'], 'valor_inicial'],
    'celular sin 10 dígitos' => [['cliente_celular' => '300123'], 'cliente_celular'],
    'cédula con letras' => [['cliente_cedula' => '10A2345'], 'cliente_cedula'],
    'valor de venta en cero' => [['valor_venta' => '0'], 'valor_venta'],
    'convenio inactivo' => [fn () => ['convenio_id' => Convenio::factory()->inactivo()->create()->id], 'convenio_id'],
]);

test('asesores only see their own recent sales and active convenios', function () {
    $asesor = User::factory()->create();
    $propia = Venta::factory()->for($asesor, 'asesor')->create();
    Venta::factory()->create();
    Convenio::factory()->inactivo()->create();

    $this->actingAs($asesor)
        ->get(route('ventas.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ventas/registrar')
            ->has('misVentas', 1)
            ->where('misVentas.0.consecutivo', $propia->consecutivo)
            ->where('misVentas.0.estado', 'pendiente')
            ->has('convenios', 2),
        );
});

test('only asesores can register sales', function () {
    $facturador = User::factory()->create(['rol' => Rol::Facturador]);

    $this->actingAs($facturador)
        ->post(route('ventas.store'), datosDeVenta())
        ->assertForbidden();

    expect(Venta::count())->toBe(0);
});
