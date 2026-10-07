<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVenta;
use App\Enums\ResponsableDevolucion;
use App\Http\Requests\GuardarVentaRequest;
use App\Http\Requests\MarcarVentaCaidaRequest;
use App\Models\Convenio;
use App\Models\ConvenioVenta;
use App\Models\Venta;
use App\Models\VentaProducto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VentaController extends Controller
{
    private const VENTAS_RECIENTES = 20;

    /**
     * Formulario del asesor, sus devueltas por corregir y sus ventas recientes.
     */
    public function create(Request $request): Response
    {
        $ventas = $request->user()->ventas()->with('productos')->latest('id');

        return Inertia::render('ventas/registrar', [
            'convenios' => $this->conveniosActivos(),
            'porCorregir' => $this->resumen(
                (clone $ventas)
                    ->where('estado', EstadoVenta::Devuelta)
                    ->where('devuelta_a', ResponsableDevolucion::Asesor)
                    ->get(),
            ),
            'misVentas' => $this->resumen(
                $ventas
                    ->whereNot(fn ($query) => $query
                        ->where('estado', EstadoVenta::Devuelta)
                        ->where('devuelta_a', ResponsableDevolucion::Asesor))
                    ->limit(self::VENTAS_RECIENTES)
                    ->get(),
            ),
        ]);
    }

    /**
     * Registra la venta a nombre del asesor que inició sesión.
     */
    public function store(GuardarVentaRequest $request): RedirectResponse
    {
        $datos = $request->datosVenta();

        $venta = DB::transaction(function () use ($request, $datos): Venta {
            $venta = $request->user()->ventas()->create($datos['venta']);
            $venta->guardarDetalle($datos['productos'], $datos['convenios']);

            return $venta;
        });

        return Inertia::flash('ventaRegistrada', $venta->consecutivo)
            ->back();
    }

    /**
     * Formulario para corregir una venta devuelta.
     */
    public function edit(Venta $venta): Response
    {
        Gate::authorize('corregir', $venta);

        $venta->load(['productos', 'conveniosVenta']);

        return Inertia::render('ventas/corregir', [
            'convenios' => $this->conveniosActivos(),
            'venta' => [
                'id' => $venta->id,
                'consecutivo' => $venta->consecutivo,
                'motivo_devolucion' => $venta->motivo_devolucion,
                'datos' => [
                    ...$venta->only(['cliente_nombre', 'cliente_cedula', 'cliente_celular']),
                    'cliente_telefono' => $venta->cliente_telefono ?? '',
                    'cliente_celular_2' => $venta->cliente_celular_2 ?? '',
                    'cliente_correo' => $venta->cliente_correo ?? '',
                    'cliente_direccion' => $venta->cliente_direccion ?? '',
                    'cliente_ciudad' => $venta->cliente_ciudad ?? '',
                    'productos' => $venta->productos->map(fn (VentaProducto $producto): array => [
                        'id' => $producto->id,
                        'articulo' => $producto->articulo,
                        'marca' => $producto->marca,
                        'referencia' => $producto->referencia,
                        'serial' => $producto->serial ?? '',
                        'valor' => (string) $producto->valor,
                    ])->values()->all(),
                    'valor_inicial' => (string) $venta->valor_inicial,
                    'forma_pago_inicial' => $venta->forma_pago_inicial->value ?? '',
                    'numero_transferencia' => $venta->numero_transferencia ?? '',
                    'convenios' => $venta->conveniosVenta->map(fn (ConvenioVenta $convenio): array => [
                        'convenio_id' => (string) $convenio->convenio_id,
                        'valor' => (string) $convenio->valor,
                        'gasto_administrativo' => (string) ($convenio->gasto_administrativo ?? ''),
                    ])->values()->all(),
                    'observaciones' => $venta->observaciones ?? '',
                ],
            ],
        ]);
    }

    /**
     * Guarda la corrección y devuelve la venta a facturación.
     */
    public function update(GuardarVentaRequest $request, Venta $venta): RedirectResponse
    {
        $datos = $request->datosVenta();

        if (! $venta->corregir($datos['venta'], $datos['productos'], $datos['convenios'])) {
            $this->avisarYaProcesada();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "VENTA #{$venta->consecutivo} corregida y enviada a facturación.",
        ]);

        return to_route('ventas.create');
    }

    /**
     * El asesor marca la venta como caída.
     */
    public function marcarCaida(MarcarVentaCaidaRequest $request, Venta $venta): RedirectResponse
    {
        if (! $venta->marcarCaida($request->validated('motivo_caida'))) {
            $this->avisarYaProcesada();
        }

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => "VENTA #{$venta->consecutivo} marcada como caída.",
        ])->back();
    }

    /**
     * @return Collection<int, Convenio>
     */
    private function conveniosActivos(): Collection
    {
        return Convenio::query()->activos()->get(['id', 'nombre', 'requiere_gasto']);
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return list<array<string, mixed>>
     */
    private function resumen(Collection $ventas): array
    {
        return array_values($ventas->map(fn (Venta $venta): array => [
            'id' => $venta->id,
            'consecutivo' => $venta->consecutivo,
            'fecha' => $venta->created_at->format('d/m/Y h:i a'),
            'cliente_nombre' => $venta->cliente_nombre,
            'articulo' => $venta->resumenProductos(),
            'valor_venta' => $venta->valor_venta,
            'estado' => $venta->estado->value,
            'devuelta_a' => $venta->devuelta_a?->value,
            'numero_factura' => $venta->numero_factura,
            'motivo_devolucion' => $venta->motivo_devolucion,
            'motivo_caida' => $venta->motivo_caida,
            'corregida' => $venta->corregida_en !== null,
            'puede_marcar_caida' => $venta->sePuedeMarcarCaida(),
        ])->all());
    }

    /**
     * @throws ValidationException
     */
    private function avisarYaProcesada(): never
    {
        throw ValidationException::withMessages([
            'venta' => 'Esta venta cambió de estado mientras la tenías abierta. Actualiza la página.',
        ]);
    }
}
