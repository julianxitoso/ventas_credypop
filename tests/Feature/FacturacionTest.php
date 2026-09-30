<?php

use App\Enums\EstadoVenta;
use App\Enums\Rol;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    $this->facturador = User::factory()->create(['rol' => Rol::Facturador]);
    $this->venta = Venta::factory()->create();
});

test('facturadores bill a pending sale with the erp invoice number', function () {
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

test('an invoice number can not be used twice', function () {
    Venta::factory()->create(['estado' => EstadoVenta::Facturada, 'numero_factura' => 'FE-1001']);

    $this->actingAs($this->facturador)
        ->post(route('ventas.facturar', $this->venta), ['numero_factura' => 'FE-1001'])
        ->assertSessionHasErrors(['numero_factura' => 'Ese número de factura ya está registrado en otra venta.']);

    expect($this->venta->refresh()->estado)->toBe(EstadoVenta::Pendiente);
});

test('facturadores return a pending sale with a mandatory reason', function () {
    $this->actingAs($this->facturador)
        ->post(route('ventas.devolver', $this->venta), ['motivo_devolucion' => ''])
        ->assertSessionHasErrors('motivo_devolucion');

    $this->actingAs($this->facturador)
        ->post(route('ventas.devolver', $this->venta), ['motivo_devolucion' => 'El serial no coincide.'])
        ->assertSessionHasNoErrors();

    $this->venta->refresh();

    expect($this->venta->estado)->toBe(EstadoVenta::Devuelta)
        ->and($this->venta->motivo_devolucion)->toBe('El serial no coincide.')
        ->and($this->venta->devuelta_por)->toBe($this->facturador->id)
        ->and($this->venta->devuelta_en)->not->toBeNull();
});

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
    'devolver' => ['ventas.devolver', ['motivo_devolucion' => 'Segundo']],
]);

test('only facturadores can bill or return sales', function (Rol $rol) {
    $user = User::factory()->create(['rol' => $rol]);

    $this->actingAs($user)
        ->post(route('ventas.facturar', $this->venta), ['numero_factura' => 'FE-3003'])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('ventas.devolver', $this->venta), ['motivo_devolucion' => 'No'])
        ->assertForbidden();

    expect($this->venta->refresh()->estado)->toBe(EstadoVenta::Pendiente);
})->with([Rol::Admin, Rol::Gerente, Rol::Asesor]);
