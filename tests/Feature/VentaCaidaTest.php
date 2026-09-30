<?php

use App\Enums\EstadoVenta;
use App\Enums\Rol;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    $this->asesor = User::factory()->create();
});

test('asesores mark their unbilled sales as fallen with a reason', function (EstadoVenta $estado) {
    $venta = Venta::factory()->for($this->asesor, 'asesor')->create(['estado' => $estado]);

    $this->actingAs($this->asesor)
        ->from(route('ventas.create'))
        ->post(route('ventas.caida', $venta), ['motivo_caida' => 'El cliente desistió.'])
        ->assertRedirect(route('ventas.create'))
        ->assertInertiaFlash('toast.type', 'success');

    $venta->refresh();

    expect($venta->estado)->toBe(EstadoVenta::Caida)
        ->and($venta->motivo_caida)->toBe('El cliente desistió.')
        ->and($venta->caida_en)->not->toBeNull();
})->with([EstadoVenta::Pendiente, EstadoVenta::Devuelta]);

test('the reason is required to mark a sale as fallen', function () {
    $venta = Venta::factory()->for($this->asesor, 'asesor')->create();

    $this->actingAs($this->asesor)
        ->post(route('ventas.caida', $venta), ['motivo_caida' => ''])
        ->assertSessionHasErrors('motivo_caida');

    expect($venta->refresh()->estado)->toBe(EstadoVenta::Pendiente);
});

test('billed or already fallen sales can not be marked as fallen', function (EstadoVenta $estado) {
    $venta = Venta::factory()->for($this->asesor, 'asesor')->create(['estado' => $estado]);

    $this->actingAs($this->asesor)
        ->post(route('ventas.caida', $venta), ['motivo_caida' => 'No'])
        ->assertForbidden();

    expect($venta->refresh()->estado)->toBe($estado);
})->with([EstadoVenta::Facturada, EstadoVenta::Caida]);

test('asesores can not mark another asesor sale as fallen', function () {
    $venta = Venta::factory()->create();

    $this->actingAs($this->asesor)
        ->post(route('ventas.caida', $venta), ['motivo_caida' => 'No'])
        ->assertForbidden();
});

test('a fallen sale can not be billed', function () {
    $venta = Venta::factory()->create(['estado' => EstadoVenta::Caida]);
    $facturador = User::factory()->create(['rol' => Rol::Facturador]);

    $this->actingAs($facturador)
        ->post(route('ventas.facturar', $venta), ['numero_factura' => 'FE-9'])
        ->assertSessionHasErrors('venta');

    expect($venta->refresh()->estado)->toBe(EstadoVenta::Caida);
});
