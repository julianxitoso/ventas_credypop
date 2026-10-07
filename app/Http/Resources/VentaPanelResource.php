<?php

namespace App\Http\Resources;

use App\Models\ConvenioVenta;
use App\Models\Venta;
use App\Models\VentaProducto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Venta
 */
class VentaPanelResource extends JsonResource
{
    private const FORMATO_FECHA = 'd/m/Y h:i a';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'consecutivo' => $this->consecutivo,
            'fecha' => $this->created_at->format(self::FORMATO_FECHA),
            'asesor' => "{$this->asesor->name} ({$this->asesor->usuario})",
            'cliente_nombre' => $this->cliente_nombre,
            'cliente_cedula' => $this->cliente_cedula,
            'cliente_celular' => $this->cliente_celular,
            'cliente_telefono' => $this->cliente_telefono,
            'cliente_celular_2' => $this->cliente_celular_2,
            'cliente_correo' => $this->cliente_correo,
            'cliente_direccion' => $this->cliente_direccion,
            'cliente_ciudad' => $this->cliente_ciudad,
            'productos_resumen' => $this->resumenProductos(),
            'serial_resumen' => $this->productos->pluck('serial')->filter()->join(', ') ?: null,
            'productos' => $this->productos->map(fn (VentaProducto $producto): array => [
                'id' => $producto->id,
                'articulo' => $producto->articulo,
                'marca' => $producto->marca,
                'referencia' => $producto->referencia,
                'serial' => $producto->serial,
                'valor' => $producto->valor,
            ])->values()->all(),
            'valor_venta' => $this->valor_venta,
            'valor_inicial' => $this->valor_inicial,
            'forma_pago_inicial' => $this->forma_pago_inicial?->etiqueta(),
            'numero_transferencia' => $this->numero_transferencia,
            'convenios' => $this->conveniosVenta->map(fn (ConvenioVenta $convenio): array => [
                'nombre' => $convenio->convenio->nombre,
                'valor' => $convenio->valor,
                'gasto_administrativo' => $convenio->gasto_administrativo,
            ])->values()->all(),
            'observaciones' => $this->observaciones,
            'estado' => $this->estado->value,
            'numero_factura' => $this->numero_factura,
            'facturada_en' => $this->facturada_en?->format(self::FORMATO_FECHA),
            'facturada_por' => $this->facturador?->name,
            'series_asignadas_en' => $this->series_asignadas_en?->format(self::FORMATO_FECHA),
            'series_asignadas_por' => $this->seriesAsignadasPor?->name,
            'motivo_devolucion' => $this->motivo_devolucion,
            'devuelta_en' => $this->devuelta_en?->format(self::FORMATO_FECHA),
            'devuelta_por' => $this->devueltaPor?->name,
            'devuelta_a' => $this->devuelta_a?->value,
            'devolucion_por_serie' => $this->devolucion_por_serie,
            'devolucion_por_otros' => $this->devolucion_por_otros,
            'revisar_serie' => $this->revisar_serie,
            'corregida_en' => $this->corregida_en?->format(self::FORMATO_FECHA),
            'nota_correccion' => $this->nota_correccion,
            'motivo_caida' => $this->motivo_caida,
            'caida_en' => $this->caida_en?->format(self::FORMATO_FECHA),
        ];
    }
}
