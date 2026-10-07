<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVenta;
use App\Http\Requests\DevolverVentaRequest;
use App\Http\Requests\FacturarVentaRequest;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FacturacionController extends Controller
{
    /**
     * Solo se facturan las ventas que ya tienen serie (pendiente con serie).
     */
    public function facturar(FacturarVentaRequest $request, Venta $venta): RedirectResponse
    {
        if (! $venta->facturar($request->validated('numero_factura'), $request->user())) {
            $this->avisarNoDisponible($venta);
        }

        return $this->volverConAviso("VENTA #{$venta->consecutivo} facturada.");
    }

    /**
     * Devuelve la venta a quien deba corregirla según el tipo de error.
     */
    public function devolver(DevolverVentaRequest $request, Venta $venta): RedirectResponse
    {
        $porSerie = $request->boolean('error_serie');
        $porOtros = $request->boolean('error_otros');

        if (! $venta->devolver($request->validated('motivo_devolucion'), $porSerie, $porOtros, $request->user())) {
            $this->avisarNoDisponible($venta);
        }

        return $this->volverConAviso(match (true) {
            $porSerie && $porOtros => "VENTA #{$venta->consecutivo} devuelta al asesor; después pasará por bodega.",
            $porOtros => "VENTA #{$venta->consecutivo} devuelta al asesor.",
            default => "VENTA #{$venta->consecutivo} devuelta a bodega para corregir la serie.",
        });
    }

    /**
     * @throws ValidationException
     */
    private function avisarNoDisponible(Venta $venta): never
    {
        throw ValidationException::withMessages([
            'venta' => $venta->fresh()?->estado === EstadoVenta::Pendiente
                ? 'Esta venta todavía no tiene serie: primero debe asignarla bodega.'
                : 'Esta venta ya fue procesada por otro usuario. Actualiza la página.',
        ]);
    }

    private function volverConAviso(string $mensaje): RedirectResponse
    {
        return Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje])->back();
    }
}
