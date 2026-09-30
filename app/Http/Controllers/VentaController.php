<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVenta;
use App\Http\Requests\GuardarVentaRequest;
use App\Http\Requests\MarcarVentaCaidaRequest;
use App\Models\Convenio;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $ventas = $request->user()->ventas()->latest('id');

        return Inertia::render('ventas/registrar', [
            'convenios' => $this->conveniosActivos(),
            'porCorregir' => $this->resumen(
                (clone $ventas)->where('estado', EstadoVenta::Devuelta)->get(),
            ),
            'misVentas' => $this->resumen(
                $ventas->where('estado', '!=', EstadoVenta::Devuelta)->limit(self::VENTAS_RECIENTES)->get(),
            ),
        ]);
    }

    /**
     * Registra la venta a nombre del asesor que inició sesión.
     */
    public function store(GuardarVentaRequest $request): RedirectResponse
    {
        $venta = $request->user()->ventas()->create($request->datosVenta());

        return Inertia::flash('ventaRegistrada', $venta->consecutivo)
            ->back();
    }

    /**
     * Formulario para corregir una venta devuelta.
     */
    public function edit(Venta $venta): Response
    {
        Gate::authorize('corregir', $venta);

        return Inertia::render('ventas/corregir', [
            'convenios' => $this->conveniosActivos(),
            'venta' => [
                'id' => $venta->id,
                'consecutivo' => $venta->consecutivo,
                'motivo_devolucion' => $venta->motivo_devolucion,
                'datos' => [
                    ...$venta->only([
                        'cliente_nombre', 'cliente_cedula', 'cliente_celular',
                        'articulo', 'marca', 'referencia', 'serial',
                    ]),
                    'cliente_correo' => $venta->cliente_correo ?? '',
                    'valor_venta' => (string) $venta->valor_venta,
                    'valor_inicial' => (string) $venta->valor_inicial,
                    'convenio_id' => (string) $venta->convenio_id,
                    'gasto_administrativo' => (string) ($venta->gasto_administrativo ?? ''),
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
        if (! $venta->corregir($request->datosVenta())) {
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
            'articulo' => $venta->articulo,
            'valor_venta' => $venta->valor_venta,
            'estado' => $venta->estado->value,
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
