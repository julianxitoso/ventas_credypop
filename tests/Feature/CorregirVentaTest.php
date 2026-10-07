<?php

use App\Enums\EstadoVenta;
use App\Enums\ResponsableDevolucion;
use App\Enums\Rol;
use App\Models\Convenio;
use App\Models\User;
use App\Models\Venta;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->asesor = User::factory()->create();
    $this->venta = Venta::factory()->for($this->asesor, 'asesor')->conSerie()->devuelta()->create([
        'motivo_devolucion' => 'La cédula está mal.',
    ]);
    $this->producto = $this->venta->productos->sole();
});

/**
 * El producto ya guardado, tal como lo devuelve el formulario de corrección.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function productoGuardado(Venta $venta, array $cambios = []): array
{
    $producto = $venta->productos->sole();

    return [
        'id' => $producto->id,
        'articulo' => $producto->articulo,
        'marca' => $producto->marca,
        'referencia' => $producto->referencia,
        'valor' => (string) $producto->valor,
        ...$cambios,
    ];
}

test('asesores see the sales returned to them apart from the recent ones', function () {
    $pendiente = Venta::factory()->for($this->asesor, 'asesor')->create();
    $enBodega = Venta::factory()->for($this->asesor, 'asesor')->conSerie()->devuelta(ResponsableDevolucion::Bodega)->create();

    $this->actingAs($this->asesor)
        ->get(route('ventas.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('porCorregir', 1)
            ->where('porCorregir.0.id', $this->venta->id)
            ->where('porCorregir.0.motivo_devolucion', 'La cédula está mal.')
            ->has('misVentas', 2)
            ->where('misVentas.0.id', $enBodega->id)
            ->where('misVentas.0.devuelta_a', 'bodega')
            ->where('misVentas.1.id', $pendiente->id),
        );
});

test('asesores open the correction form with the sale data', function () {
    $this->actingAs($this->asesor)
        ->get(route('ventas.edit', $this->venta))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ventas/corregir')
            ->where('venta.motivo_devolucion', 'La cédula está mal.')
            ->has('venta.datos.productos', 1)
            ->where('venta.datos.productos.0.id', $this->producto->id)
            ->where('venta.datos.productos.0.serial', $this->producto->serial)
            ->where('venta.datos.productos.0.valor', '1250000')
            ->has('venta.datos.convenios', 1)
            ->where('venta.datos.convenios.0.valor', '1250000'),
        );
});

test('a corrected sale that keeps every serial goes straight to billing', function () {
    $this->actingAs($this->asesor)
        ->put(route('ventas.update', $this->venta), datosDeVenta([
            'cliente_cedula' => '1069999999',
            'productos' => [productoGuardado($this->venta, ['referencia' => 'UN55-NUEVA', 'serial' => 'SN-INTENTO'])],
        ]))
        ->assertRedirect(route('ventas.create'))
        ->assertInertiaFlash('toast.type', 'success');

    $this->venta->refresh();

    expect($this->venta->estado)->toBe(EstadoVenta::ConSerie)
        ->and($this->venta->cliente_cedula)->toBe('1069999999')
        ->and($this->venta->con_serie_en)->not->toBeNull()
        ->and($this->venta->corregida_en)->not->toBeNull()
        ->and($this->venta->productos->sole()->only(['id', 'referencia', 'serial']))->toBe([
            'id' => $this->producto->id,
            'referencia' => 'UN55-NUEVA',
            'serial' => $this->producto->serial,
        ]);
});

test('a corrected sale with a new product goes back to bodega', function () {
    $brilla = Convenio::factory()->create();
    $addi = Convenio::factory()->create();

    $this->actingAs($this->asesor)
        ->put(route('ventas.update', $this->venta), datosDeVenta([
            'productos' => [
                productoGuardado($this->venta, ['valor' => '1000000']),
                ['articulo' => 'Soporte', 'marca' => 'Genérico', 'referencia' => 'S1', 'valor' => '100000'],
            ],
            'convenios' => [convenioDeVenta($brilla, '600000'), convenioDeVenta($addi, '500000')],
        ]))
        ->assertSessionHasNoErrors();

    $this->venta->refresh();

    expect($this->venta->estado)->toBe(EstadoVenta::Pendiente)
        ->and($this->venta->valor_venta)->toBe(1_100_000)
        ->and($this->venta->productos->pluck('serial')->all())->toBe([$this->producto->serial, null])
        ->and($this->venta->convenios->pluck('id')->all())->toBe([$brilla->id, $addi->id])
        ->and($this->venta->asesor_id)->toBe($this->asesor->id)
        ->and($this->venta->motivo_devolucion)->toBe('La cédula está mal.');
});

test('when the serial was also wrong the corrected sale goes back to bodega', function () {
    $this->venta->forceFill(['devolucion_por_serie' => true, 'revisar_serie' => true])->save();

    $this->actingAs($this->asesor)
        ->put(route('ventas.update', $this->venta), datosDeVenta(['productos' => [productoGuardado($this->venta)]]))
        ->assertSessionHasNoErrors();

    expect($this->venta->refresh())
        ->estado->toBe(EstadoVenta::Pendiente)
        ->revisar_serie->toBeTrue()
        ->and($this->venta->productos->sole()->serial)->toBe($this->producto->serial);
});

test('the correction uses the same validation as a new sale', function () {
    $this->actingAs($this->asesor)
        ->put(route('ventas.update', $this->venta), datosDeVenta(['cliente_celular' => '123']))
        ->assertSessionHasErrors('cliente_celular');

    expect($this->venta->refresh()->estado)->toBe(EstadoVenta::Devuelta);
});

test('only sales returned to the asesor can be corrected', function (array $cambios) {
    $this->venta->forceFill($cambios)->save();

    $this->actingAs($this->asesor)->get(route('ventas.edit', $this->venta))->assertForbidden();
    $this->actingAs($this->asesor)->put(route('ventas.update', $this->venta), datosDeVenta())->assertForbidden();
})->with([
    'pendiente' => [['estado' => EstadoVenta::Pendiente]],
    'con serie' => [['estado' => EstadoVenta::ConSerie]],
    'facturada' => [['estado' => EstadoVenta::Facturada]],
    'caída' => [['estado' => EstadoVenta::Caida]],
    'devuelta a bodega' => [['devuelta_a' => ResponsableDevolucion::Bodega]],
]);

test('asesores can not correct another asesor sale', function () {
    $otro = User::factory()->create();

    $this->actingAs($otro)->get(route('ventas.edit', $this->venta))->assertForbidden();
    $this->actingAs($otro)->put(route('ventas.update', $this->venta), datosDeVenta())->assertForbidden();
});

test('facturadores can not correct sales', function () {
    $facturador = User::factory()->create(['rol' => Rol::Facturador]);

    $this->actingAs($facturador)->put(route('ventas.update', $this->venta), datosDeVenta())->assertForbidden();
});
