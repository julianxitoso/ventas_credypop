<?php

use App\Enums\EstadoVenta;
use App\Enums\Rol;
use App\Models\User;
use App\Models\Venta;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->facturador = User::factory()->create(['rol' => Rol::Facturador]);
});

test('the panel shows todays indicators', function () {
    Venta::factory()->count(2)->create(['valor_venta' => 1_000_000]);
    Venta::factory()->create(['estado' => EstadoVenta::Facturada, 'facturada_en' => now()]);
    Venta::factory()->create(['estado' => EstadoVenta::Facturada, 'facturada_en' => now()->subDay()]);
    Venta::factory()->create(['estado' => EstadoVenta::Devuelta, 'devuelta_en' => now()]);

    $this->actingAs($this->facturador)
        ->get(route('panel'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/index')
            ->where('indicadores', [
                'pendientes' => 2,
                'facturadasHoy' => 1,
                'devueltasHoy' => 1,
                'valorPendiente' => 2_000_000,
            ])
            ->where('puedeFacturar', true),
        );
});

test('the panel reports the latest sale and correction so it can announce new work', function () {
    Venta::factory()->create();
    $ultima = Venta::factory()->create([
        'estado' => EstadoVenta::Pendiente,
        'corregida_en' => '2026-09-30 10:15:00',
    ]);

    $this->actingAs($this->facturador)
        ->get(route('panel', ['pestana' => 'historial', 'buscar' => 'no-coincide']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('ventas.data', 0)
            ->where('ultimaVentaId', $ultima->id)
            ->where('ultimaCorreccion', '2026-09-30 10:15:00'),
        );
});

test('the pending tab only lists pending sales, newest first', function () {
    $antigua = Venta::factory()->create();
    $reciente = Venta::factory()->create();
    Venta::factory()->create(['estado' => EstadoVenta::Facturada]);

    $this->actingAs($this->facturador)
        ->get(route('panel'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('ventas.data', 2)
            ->where('ventas.data.0.id', $reciente->id)
            ->where('ventas.data.1.id', $antigua->id),
        );
});

test('the history tab lists every sale and can filter by status', function () {
    Venta::factory()->create();
    Venta::factory()->create(['estado' => EstadoVenta::Devuelta]);
    Venta::factory()->create(['estado' => EstadoVenta::Facturada]);

    $this->actingAs($this->facturador)
        ->get(route('panel', ['pestana' => 'historial']))
        ->assertInertia(fn (Assert $page) => $page->has('ventas.data', 3));

    $this->actingAs($this->facturador)
        ->get(route('panel', ['pestana' => 'historial', 'estado' => 'devuelta']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('ventas.data', 1)
            ->where('ventas.data.0.estado', 'devuelta'),
        );
});

test('sales can be searched by client, id number, serial or sale number', function (string $busqueda) {
    $buscada = Venta::factory()->create([
        'cliente_nombre' => 'Carlos Muñoz',
        'cliente_cedula' => '1061998877',
        'serial' => 'SN-ABC-123',
    ]);
    Venta::factory()->create();

    $busqueda = str_replace(':consecutivo', $buscada->consecutivo, $busqueda);

    $this->actingAs($this->facturador)
        ->get(route('panel', ['pestana' => 'historial', 'buscar' => $busqueda]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('ventas.data', 1)
            ->where('ventas.data.0.id', $buscada->id),
        );
})->with([
    'cliente' => 'carlos',
    'cédula' => '1061998877',
    'serial' => 'ABC-123',
    'número de venta' => 'VENTA #:consecutivo',
]);

test('admins see the panel without billing permission', function () {
    $admin = User::factory()->create(['rol' => Rol::Admin]);

    $this->actingAs($admin)
        ->get(route('panel'))
        ->assertInertia(fn (Assert $page) => $page->where('puedeFacturar', false));
});

test('the report downloads the filtered sales as an Excel friendly csv', function () {
    Venta::factory()->create([
        'estado' => EstadoVenta::Devuelta,
        'cliente_nombre' => 'Ana Pérez',
        'observaciones' => '=HYPERLINK("http://malo")',
    ]);
    Venta::factory()->create(['cliente_nombre' => 'No Incluida']);

    $response = $this->actingAs($this->facturador)
        ->get(route('panel.informe', ['pestana' => 'historial', 'estado' => 'devuelta']));

    $response->assertOk()->assertDownload();

    $csv = $response->streamedContent();
    $lineas = explode("\n", trim($csv));

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($lineas)->toHaveCount(2)
        ->and($lineas[0])->toContain('Venta;Fecha;Asesor')
        ->and($lineas[1])->toContain('Ana Pérez')
        ->and($lineas[1])->toContain(';Devuelta;')
        ->and($lineas[1])->toContain("'=HYPERLINK")
        ->and($csv)->not->toContain('No Incluida');
});

test('asesores can not open the panel nor download the report', function () {
    $asesor = User::factory()->create();

    $this->actingAs($asesor)->get(route('panel'))->assertForbidden();
    $this->actingAs($asesor)->get(route('panel.informe'))->assertForbidden();
});
