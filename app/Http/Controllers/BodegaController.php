<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVenta;
use App\Enums\ResponsableDevolucion;
use App\Http\Requests\AsignarSeriesRequest;
use App\Http\Requests\DevolverDesdeBodegaRequest;
use App\Http\Resources\VentaPanelResource;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla de bodega: ventas que esperan serie (pendientes y devueltas a
 * bodega). Bodega asigna la serie de cada producto o devuelve la venta al asesor.
 */
class BodegaController extends Controller
{
    private const VENTAS_POR_PAGINA = 25;

    /**
     * La página se actualiza sola cada 30 segundos; "ultimaVentaId",
     * "ultimaDevolucion" y "ultimaCorreccion" le permiten avisar cuando llega
     * trabajo nuevo.
     */
    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->limit(100, '')->value();

        return Inertia::render('bodega/index', [
            'buscar' => $buscar,
            'indicadores' => [
                'porAsignar' => Venta::query()->where('estado', EstadoVenta::Pendiente)->count(),
                'devueltas' => Venta::query()
                    ->where('estado', EstadoVenta::Devuelta)
                    ->where('devuelta_a', ResponsableDevolucion::Bodega)
                    ->count(),
                'asignadasHoy' => Venta::query()->where('series_asignadas_en', '>=', today())->count(),
            ],
            'ventas' => VentaPanelResource::collection(
                Venta::query()
                    ->with(['asesor:id,name,usuario', 'productos', 'conveniosVenta.convenio:id,nombre', 'devueltaPor:id,name', 'seriesAsignadasPor:id,name', 'facturador:id,name'])
                    ->aCargoDeBodega()
                    ->buscar($buscar)
                    ->latest('id')
                    ->paginate(self::VENTAS_POR_PAGINA)
                    ->withQueryString(),
            ),
            'ultimaVentaId' => (int) Venta::query()->max('id'),
            'ultimaDevolucion' => Venta::query()
                ->where('devuelta_a', ResponsableDevolucion::Bodega)
                ->max('devuelta_en'),
            'ultimaCorreccion' => Venta::query()
                ->where('estado', EstadoVenta::Pendiente)
                ->max('corregida_en'),
        ]);
    }

    /**
     * Asigna la serie de cada producto: la venta pasa a "pendiente con serie".
     */
    public function asignarSeries(AsignarSeriesRequest $request, Venta $venta): RedirectResponse
    {
        if (! $venta->asignarSeries($request->series(), $request->user())) {
            $this->avisarYaProcesada();
        }

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => "VENTA #{$venta->consecutivo}: series asignadas, lista para facturar.",
        ])->back();
    }

    /**
     * Devuelve la venta al asesor por un problema que no es de serie.
     */
    public function devolver(DevolverDesdeBodegaRequest $request, Venta $venta): RedirectResponse
    {
        if (! $venta->devolverDesdeBodega($request->validated('motivo_devolucion'), $request->user())) {
            $this->avisarYaProcesada();
        }

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => "VENTA #{$venta->consecutivo} devuelta al asesor.",
        ])->back();
    }

    /**
     * @throws ValidationException
     */
    private function avisarYaProcesada(): never
    {
        throw ValidationException::withMessages([
            'venta' => 'Esta venta ya no está a cargo de bodega (otro usuario la procesó). Actualiza la página.',
        ]);
    }
}
