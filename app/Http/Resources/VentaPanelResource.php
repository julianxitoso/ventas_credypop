<?php

namespace App\Http\Resources;

use App\Models\Venta;
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
            'articulo' => $this->articulo,
            'marca' => $this->marca,
            'referencia' => $this->referencia,
            'serial' => $this->serial,
            'valor_venta' => $this->valor_venta,
            'valor_inicial' => $this->valor_inicial,
            'forma_pago_inicial' => $this->forma_pago_inicial?->etiqueta(),
            'numero_transferencia' => $this->numero_transferencia,
            'convenio' => $this->convenio->nombre,
            'gasto_administrativo' => $this->gasto_administrativo,
            'observaciones' => $this->observaciones,
            'estado' => $this->estado->value,
            'numero_factura' => $this->numero_factura,
            'facturada_en' => $this->facturada_en?->format(self::FORMATO_FECHA),
            'facturada_por' => $this->facturador?->name,
            'motivo_devolucion' => $this->motivo_devolucion,
            'devuelta_en' => $this->devuelta_en?->format(self::FORMATO_FECHA),
            'devuelta_por' => $this->devueltaPor?->name,
            'corregida_en' => $this->corregida_en?->format(self::FORMATO_FECHA),
            'motivo_caida' => $this->motivo_caida,
            'caida_en' => $this->caida_en?->format(self::FORMATO_FECHA),
        ];
    }
}
