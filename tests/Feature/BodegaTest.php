<?php

use App\Enums\EstadoVenta;
use App\Enums\ResponsableDevolucion;
use App\Enums\Rol;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaProducto;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->bodega = User::factory()->create(['rol' => Rol::Bodega]);
    $this->venta = Venta::factory()
        ->has(VentaProducto::factory()->count(2)->sequence(['valor' => 1_000_000], ['valor' => 250_000]), 'productos')
        ->create();
    [$this->televisor, $this->soporte] = $this->venta->productos->all();
});

test('bodega sees the sales waiting for a serial and the ones returned to it', function () {
    $devueltaABodega = Venta::factory()->conSerie()->devuelta(ResponsableDevolucion::Bodega)->create();
    Venta::factory()->conSerie()->create();
    Venta::factory()->devuelta()->create();
    Venta::factory()->create(['estado' => EstadoVenta::Facturada]);

    $this->actingAs($this->bodega)
        ->get(route('bodega'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('bodega/index')
            ->where('indicadores', ['porAsignar' => 1, 'devueltas' => 1, 'asignadasHoy' => 2])
            ->has('ventas.data', 2)
            ->where('ventas.data.0.id', $devueltaABodega->id)
            ->where('ventas.data.1.id', $this->venta->id)
            ->has('ventas.data.1.productos', 2),
        );
});

test('bodega assigns a serial to every product and the sale is ready to bill', function () {
    $this->actingAs($this->bodega)
        ->from(route('bodega'))
        ->post(route('bodega.series', $this->venta), ['series' => [
            $this->televisor->id => ' SN-TV-1 ',
            $this->soporte->id => 'SN-SOP-1',
        ]])
        ->assertRedirect(route('bodega'))
        ->assertInertiaFlash('toast.type', 'success');

    $this->venta->refresh();

    expect($this->venta->estado)->toBe(EstadoVenta::ConSerie)
        ->and($this->venta->productos->pluck('serial')->all())->toBe(['SN-TV-1', 'SN-SOP-1'])
        ->and($this->venta->series_asignadas_por)->toBe($this->bodega->id)
        ->and($this->venta->series_asignadas_en)->not->toBeNull()
        ->and($this->venta->con_serie_en)->not->toBeNull();
});

test('bodega corrects the serial of a sale returned for a wrong serial', function () {
    $venta = Venta::factory()->conSerie()->devuelta(ResponsableDevolucion::Bodega)->create();
    $producto = $venta->productos->sole();

    $this->actingAs($this->bodega)
        ->post(route('bodega.series', $venta), ['series' => [$producto->id => 'SN-CORREGIDA']])
        ->assertSessionHasNoErrors();

    expect($venta->refresh()->estado)->toBe(EstadoVenta::ConSerie)
        ->and($producto->refresh()->serial)->toBe('SN-CORREGIDA');
});

test('every product needs its own serial', function (array $series, string $campo, string $mensaje) {
    $ids = ['televisor' => $this->televisor->id, 'soporte' => $this->soporte->id];
    $seriesAntes = $this->venta->productos->pluck('serial')->all();

    $this->actingAs($this->bodega)
        ->post(route('bodega.series', $this->venta), ['series' => collect($series)->mapWithKeys(fn ($serie, $producto) => [$ids[$producto] => $serie])->all()])
        ->assertSessionHasErrors(["series.{$ids[$campo]}" => $mensaje]);

    expect($this->venta->refresh()->estado)->toBe(EstadoVenta::Pendiente)
        ->and($this->venta->productos->pluck('serial')->all())->toBe($seriesAntes);
})->with([
    'falta una' => [['televisor' => 'SN-1'], 'soporte', 'Falta la serie de este producto.'],
    'vacía' => [['televisor' => 'SN-1', 'soporte' => ' '], 'soporte', 'El campo serie es obligatorio.'],
    'repetida en la venta' => [['televisor' => 'SN-1', 'soporte' => 'sn-1'], 'televisor', 'Esta serie está repetida en la venta.'],
]);

test('a serial can not be used in another active sale', function () {
    $otra = Venta::factory()->conSerie()->create();
    $serieUsada = $otra->productos->sole()->serial;
    Venta::factory()->has(VentaProducto::factory()->state(['serial' => 'SN-DE-CAIDA']), 'productos')->create(['estado' => EstadoVenta::Caida]);

    $this->actingAs($this->bodega)
        ->post(route('bodega.series', $this->venta), ['series' => [
            $this->televisor->id => $serieUsada,
            $this->soporte->id => 'SN-DE-CAIDA',
        ]])
        ->assertSessionHasErrors(["series.{$this->televisor->id}" => "Esta serie ya está asignada en la VENTA #{$otra->consecutivo}."])
        ->assertSessionDoesntHaveErrors("series.{$this->soporte->id}");
});

test('bodega returns a sale to the asesor with a reason', function () {
    $this->actingAs($this->bodega)
        ->post(route('bodega.devolver', $this->venta), ['motivo_devolucion' => ''])
        ->assertSessionHasErrors('motivo_devolucion');

    $this->actingAs($this->bodega)
        ->post(route('bodega.devolver', $this->venta), ['motivo_devolucion' => 'La referencia no existe.'])
        ->assertSessionHasNoErrors();

    expect($this->venta->refresh())
        ->estado->toBe(EstadoVenta::Devuelta)
        ->devuelta_a->toBe(ResponsableDevolucion::Asesor)
        ->motivo_devolucion->toBe('La referencia no existe.')
        ->devuelta_por->toBe($this->bodega->id)
        ->revisar_serie->toBeTrue()
        ->and($this->venta->sePuedeCorregir())->toBeTrue();
});

test('bodega can only act on sales in its charge', function (array $estado) {
    $this->venta->forceFill($estado)->save();

    $this->actingAs($this->bodega)
        ->post(route('bodega.series', $this->venta), ['series' => [
            $this->televisor->id => 'SN-1',
            $this->soporte->id => 'SN-2',
        ]])
        ->assertSessionHasErrors('venta');

    $this->actingAs($this->bodega)
        ->post(route('bodega.devolver', $this->venta), ['motivo_devolucion' => 'No'])
        ->assertSessionHasErrors('venta');

    expect($this->venta->refresh()->estado)->toBe($estado['estado']);
})->with([
    'con serie' => [['estado' => EstadoVenta::ConSerie]],
    'facturada' => [['estado' => EstadoVenta::Facturada]],
    'caída' => [['estado' => EstadoVenta::Caida]],
    'devuelta al asesor' => [['estado' => EstadoVenta::Devuelta, 'devuelta_a' => ResponsableDevolucion::Asesor]],
]);

test('only bodega can assign serials or return from bodega', function (Rol $rol) {
    $user = User::factory()->create(['rol' => $rol]);

    $this->actingAs($user)
        ->post(route('bodega.series', $this->venta), ['series' => [$this->televisor->id => 'SN-1', $this->soporte->id => 'SN-2']])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('bodega.devolver', $this->venta), ['motivo_devolucion' => 'No'])
        ->assertForbidden();

    expect($this->venta->refresh()->estado)->toBe(EstadoVenta::Pendiente);
})->with([Rol::Asesor, Rol::Facturador, Rol::Gerente, Rol::Admin]);
