<?php

namespace App\Http\Controllers;

use App\Http\Requests\DevolverVentaRequest;
use App\Http\Requests\FacturarVentaRequest;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FacturacionController extends Controller
{
    public function facturar(FacturarVentaRequest $request, Venta $venta): RedirectResponse
    {
        if (! $venta->facturar($request->validated('numero_factura'), $request->user())) {
            $this->avisarYaProcesada();
        }

        return $this->volverConAviso("VENTA #{$venta->consecutivo} facturada.");
    }

    public function devolver(DevolverVentaRequest $request, Venta $venta): RedirectResponse
    {
        if (! $venta->devolver($request->validated('motivo_devolucion'), $request->user())) {
            $this->avisarYaProcesada();
        }

        return $this->volverConAviso("VENTA #{$venta->consecutivo} devuelta al asesor.");
    }

    /**
     * @throws ValidationException
     */
    private function avisarYaProcesada(): never
    {
        throw ValidationException::withMessages([
            'venta' => 'Esta venta ya fue procesada por otro usuario. Actualiza la página.',
        ]);
    }

    private function volverConAviso(string $mensaje): RedirectResponse
    {
        return Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje])->back();
    }
}
