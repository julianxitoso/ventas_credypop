<?php

use App\Enums\EstadoVenta;
use App\Enums\ResponsableDevolucion;
use App\Enums\Rol;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    $this->facturador = User::factory()->create(['rol' => Rol::Facturador]);
    $this->venta = Venta::factory()->conSerie()->create();
});

test('facturadores bill a sale with serials using the erp invoice number', function () {
    $this->actingAs($this->facturador)
        ->from(route('panel'))
        ->post(route('ventas.facturar', $this->venta), ['numero_factura' => 'FE-1001'])
        ->assertRedirect(route('panel'))
        ->assertInertiaFlash('toast.type', 'success');

    $this->venta->refresh();

    expect($this->venta->estado)->toBe(EstadoVenta::Facturada)
        ->and($this->venta->numero_factura)->toBe('FE-1001')
        ->and($this->venta->facturada_por)->toBe($this->facturador->id)
        ->and($this->venta->facturada_en)->not->toBeNull();
});

test('a sale without serials can not be billed or returned yet', function (string $ruta, array $datos) {
    $venta = Venta::factory()->create();

    $this->actingAs($this->facturador)
        ->post(route($ruta, $venta), $datos)
        ->assertSessionHasErrors(['venta' => 'Esta venta todavía no tiene serie: primero debe asignarla bodega.']);

    expect($venta->refresh()->estado)->toBe(EstadoVenta::Pendiente);
})->with([
    'facturar' => ['ventas.facturar', ['numero_factura' => 'FE-1001']],
    'devolver' => ['ventas.devolver', ['motivo_devolucion' => 'No', 'error_otros' => true]],
]);

test('an invoice number can not be used twice', function () {
    Venta::factory()->create(['estado' => EstadoVenta::Facturada, 'numero_factura' => 'FE-1001']);

    $this->actingAs($this->facturador)
        ->post(route('ventas.facturar', $this->venta), ['numero_factura' => 'FE-1001'])
        ->assertSessionHasErrors(['numero_factura' => 'Ese número de factura ya está registrado en otra venta.']);

    expect($this->venta->refresh()->estado)->toBe(EstadoVenta::ConSerie);
});

test('a return needs a reason and the kind of error', function () {
    $this->actingAs($this->facturador)
        ->post(route('ventas.devolver', $this->venta), ['motivo_devolucion' => ''])
        ->assertSessionHasErrors([
            'motivo_devolucion',
            'error_serie' => 'Indica si el error es de la serie, de otros datos o de ambos.',
        ]);

    expect($this->venta->refresh()->estado)->toBe(EstadoVenta::ConSerie);
});

test('the kind of error decides who corrects the returned sale', function (array $errores, ResponsableDevolucion $devueltaA, bool $revisarSerie) {
    $this->actingAs($this->facturador)
        ->post(route('ventas.devolver', $this->venta), ['motivo_devolucion' => 'Hay un error.', ...$errores])
        ->assertSessionHasNoErrors();

    expect($this->venta->refresh())
        ->estado->toBe(EstadoVenta::Devuelta)
        ->motivo_devolucion->toBe('Hay un error.')
        ->devuelta_por->toBe($this->facturador->id)
        ->devuelta_en->not->toBeNull()
        ->devuelta_a->toBe($devueltaA)
        ->devolucion_por_serie->toBe($errores['error_serie'] ?? false)
        ->devolucion_por_otros->toBe($errores['error_otros'] ?? false)
        ->revisar_serie->toBe($revisarSerie);
})->with([
    'solo la serie' => [['error_serie' => true], ResponsableDevolucion::Bodega, false],
    'solo otros datos' => [['error_otros' => true], ResponsableDevolucion::Asesor, false],
    'ambos' => [['error_serie' => true, 'error_otros' => true], ResponsableDevolucion::Asesor, true],
]);

test('a sale that was already processed can not be processed again', function (string $ruta, array $datos) {
    $this->venta->forceFill(['estado' => EstadoVenta::Devuelta, 'motivo_devolucion' => 'Primero'])->save();

    $this->actingAs($this->facturador)
        ->post(route($ruta, $this->venta), $datos)
        ->assertSessionHasErrors('venta');

    expect($this->venta->refresh())
        ->estado->toBe(EstadoVenta::Devuelta)
        ->motivo_devolucion->toBe('Primero');
})->with([
    'facturar' => ['ventas.facturar', ['numero_factura' => 'FE-2002']],
    'devolver' => ['ventas.devolver', ['motivo_devolucion' => 'Segundo', 'error_otros' => true]],
]);

test('only facturadores can bill or return sales', function (Rol $rol) {
    $user = User::factory()->create(['rol' => $rol]);

    $this->actingAs($user)
        ->post(route('ventas.facturar', $this->venta), ['numero_factura' => 'FE-3003'])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('ventas.devolver', $this->venta), ['motivo_devolucion' => 'No', 'error_otros' => true])
        ->assertForbidden();

    expect($this->venta->refresh()->estado)->toBe(EstadoVenta::ConSerie);
})->with([Rol::Admin, Rol::Gerente, Rol::Asesor, Rol::Bodega]);
