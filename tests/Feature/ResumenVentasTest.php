<?php

use App\Enums\EstadoVenta;
use App\Enums\Rol;
use App\Models\Convenio;
use App\Models\User;
use App\Models\Venta;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 17)->setTime(15, 0));

    $this->gerente = User::factory()->create(['rol' => Rol::Gerente]);
    $this->ana = User::factory()->create(['name' => 'Ana']);
    $this->luis = User::factory()->create(['name' => 'Luis']);
    $this->contado = Convenio::factory()->create(['nombre' => 'Contado']);
    $this->addi = Convenio::factory()->create(['nombre' => 'Addi']);

    $venta = fn (User $asesor, Convenio $convenio, int $valor, int $inicial, EstadoVenta $estado, string $fecha) => Venta::factory()
        ->for($asesor, 'asesor')
        ->for($convenio)
        ->create(['valor_venta' => $valor, 'valor_inicial' => $inicial, 'estado' => $estado, 'created_at' => $fecha]);

    $venta($this->ana, $this->contado, 1_000_000, 200_000, EstadoVenta::Facturada, '2026-09-16 09:00');
    $venta($this->ana, $this->addi, 2_000_000, 0, EstadoVenta::Pendiente, '2026-09-17 10:00');
    $venta($this->luis, $this->contado, 500_000, 100_000, EstadoVenta::Devuelta, '2026-09-17 11:00');
    $venta($this->luis, $this->addi, 9_000_000, 900_000, EstadoVenta::Caida, '2026-09-02 08:00');
    $venta($this->luis, $this->contado, 7_000_000, 700_000, EstadoVenta::Facturada, '2026-08-20 08:00');
});

test('only billed sales count as sold and down payments count for every sale that did not fall', function () {
    $this->actingAs($this->gerente)
        ->get(route('resumen'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('resumen/index')
            ->where('periodo', 'mes')
            ->where('rango', '01/09/2026 – 17/09/2026')
            ->where('indicadores', [
                'valorVendido' => 1_000_000,
                'porFacturar' => 2_500_000,
                'totalRegistrado' => 3_500_000,
                'cuotaInicial' => 300_000,
                'ventasConInicial' => 2,
                'valorSinIniciales' => 800_000,
                'facturadas' => 1,
                'pendientes' => 1,
                'devueltas' => 1,
                'caidas' => 1,
            ])
            ->has('porDia', 17)
            ->where('porDia.15', [
                'dia' => '2026-09-16', 'etiqueta' => '16/09', 'cantidad' => 1, 'valor' => 1_000_000,
                'facturadas' => 1, 'pendientes' => 0, 'devueltas' => 0,
            ])
            ->where('porDia.16', [
                'dia' => '2026-09-17', 'etiqueta' => '17/09', 'cantidad' => 2, 'valor' => 2_500_000,
                'facturadas' => 0, 'pendientes' => 1, 'devueltas' => 1,
            ])
            ->where('porDia.1.cantidad', 0),
        );
});

test('the dashboard groups sales by asesor and by convenio', function () {
    $this->actingAs($this->gerente)
        ->get(route('resumen'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('porAsesor', 2)
            ->where('porAsesor.0.nombre', 'Ana')
            ->where('porAsesor.0.ventas', 2)
            ->where('porAsesor.0.valor', 1_000_000)
            ->where('porAsesor.0.porFacturar', 2_000_000)
            ->where('porAsesor.0.inicial', 200_000)
            ->where('porAsesor.0.facturadas', 1)
            ->where('porAsesor.1.nombre', 'Luis')
            ->where('porAsesor.1.ventas', 2)
            ->where('porAsesor.1.valor', 0)
            ->where('porAsesor.1.porFacturar', 500_000)
            ->where('porAsesor.1.inicial', 100_000)
            ->where('porAsesor.1.devueltas', 1)
            ->where('porAsesor.1.caidas', 1)
            ->where('porConvenio', [
                ['nombre' => 'Contado', 'ventas' => 2, 'valor' => 1_000_000, 'porFacturar' => 500_000, 'inicial' => 300_000, 'color' => 0],
                ['nombre' => 'Addi', 'ventas' => 1, 'valor' => 0, 'porFacturar' => 2_000_000, 'inicial' => 0, 'color' => 1],
            ]),
        );
});

test('each convenio keeps its color in any period', function () {
    $this->actingAs($this->gerente)
        ->get(route('resumen', ['periodo' => 'mes_anterior']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('porConvenio.0.nombre', 'Contado')
            ->where('porConvenio.0.color', 0),
        );
});

test('the dashboard can show other periods', function (string $periodo, int $totalRegistrado, int $dias) {
    $this->actingAs($this->gerente)
        ->get(route('resumen', ['periodo' => $periodo]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('periodo', $periodo)
            ->where('indicadores.totalRegistrado', $totalRegistrado)
            ->has('porDia', $dias),
        );
})->with([
    'hoy' => ['hoy', 2_500_000, 1],
    'semana (lunes 14 al jueves 17)' => ['semana', 3_500_000, 4],
    'mes anterior' => ['mes_anterior', 7_000_000, 31],
]);
