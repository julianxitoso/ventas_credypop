<?php

use App\Enums\EstadoVenta;
use App\Enums\Rol;
use App\Models\User;
use App\Models\Venta;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->asesor = User::factory()->create();
    $this->venta = Venta::factory()->for($this->asesor, 'asesor')->create([
        'estado' => EstadoVenta::Devuelta,
        'motivo_devolucion' => 'El serial no coincide.',
        'devuelta_en' => now(),
    ]);
});

test('asesores see their returned sales apart from the recent ones', function () {
    $pendiente = Venta::factory()->for($this->asesor, 'asesor')->create();

    $this->actingAs($this->asesor)
        ->get(route('ventas.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('porCorregir', 1)
            ->where('porCorregir.0.id', $this->venta->id)
            ->where('porCorregir.0.motivo_devolucion', 'El serial no coincide.')
            ->has('misVentas', 1)
            ->where('misVentas.0.id', $pendiente->id),
        );
});

test('asesores open the correction form with the sale data', function () {
    $this->actingAs($this->asesor)
        ->get(route('ventas.edit', $this->venta))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ventas/corregir')
            ->where('venta.motivo_devolucion', 'El serial no coincide.')
            ->where('venta.datos.serial', $this->venta->serial)
            ->where('venta.datos.valor_venta', '1250000'),
        );
});

test('a corrected sale goes back to billing as pending', function () {
    $this->actingAs($this->asesor)
        ->put(route('ventas.update', $this->venta), datosDeVenta(['serial' => 'SN-CORREGIDO']))
        ->assertRedirect(route('ventas.create'))
        ->assertInertiaFlash('toast.type', 'success');

    $this->venta->refresh();

    expect($this->venta->estado)->toBe(EstadoVenta::Pendiente)
        ->and($this->venta->serial)->toBe('SN-CORREGIDO')
        ->and($this->venta->asesor_id)->toBe($this->asesor->id)
        ->and($this->venta->corregida_en)->not->toBeNull()
        ->and($this->venta->motivo_devolucion)->toBe('El serial no coincide.');
});

test('the correction uses the same validation as a new sale', function () {
    $this->actingAs($this->asesor)
        ->put(route('ventas.update', $this->venta), datosDeVenta(['cliente_celular' => '123']))
        ->assertSessionHasErrors('cliente_celular');

    expect($this->venta->refresh()->estado)->toBe(EstadoVenta::Devuelta);
});

test('only returned sales can be corrected', function (EstadoVenta $estado) {
    $this->venta->forceFill(['estado' => $estado])->save();

    $this->actingAs($this->asesor)->get(route('ventas.edit', $this->venta))->assertForbidden();
    $this->actingAs($this->asesor)->put(route('ventas.update', $this->venta), datosDeVenta())->assertForbidden();
})->with([EstadoVenta::Pendiente, EstadoVenta::Facturada, EstadoVenta::Caida]);

test('asesores can not correct another asesor sale', function () {
    $otro = User::factory()->create();

    $this->actingAs($otro)->get(route('ventas.edit', $this->venta))->assertForbidden();
    $this->actingAs($otro)->put(route('ventas.update', $this->venta), datosDeVenta())->assertForbidden();
});

test('facturadores can not correct sales', function () {
    $facturador = User::factory()->create(['rol' => Rol::Facturador]);

    $this->actingAs($facturador)->put(route('ventas.update', $this->venta), datosDeVenta())->assertForbidden();
});
