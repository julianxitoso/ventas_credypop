<?php

namespace App\Http\Requests;

use App\Enums\EstadoVenta;
use App\Models\Venta;
use App\Models\VentaProducto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Bodega asigna la serie de cada producto de la venta. Todas son obligatorias,
 * no se pueden repetir en la misma venta ni estar en otra venta activa.
 */
class AsignarSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('asignar-series');
    }

    /**
     * Quita espacios al inicio y al final de cada serie.
     */
    protected function prepareForValidation(): void
    {
        $series = $this->input('series');

        if (is_array($series)) {
            $this->merge([
                'series' => array_map(fn ($serie) => is_string($serie) ? trim($serie) : $serie, $series),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'series' => ['required', 'array'],
            'series.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $series = $this->series();

                foreach ($this->venta()->productos()->pluck('id') as $productoId) {
                    if (! isset($series[$productoId])) {
                        $validator->errors()->add("series.{$productoId}", 'Falta la serie de este producto.');
                    }
                }

                $enOtrasVentas = VentaProducto::query()
                    ->with('venta:id')
                    ->whereIn('serial', array_values($series))
                    ->where('venta_id', '!=', $this->venta()->id)
                    ->whereHas('venta', fn ($venta) => $venta->where('estado', '!=', EstadoVenta::Caida))
                    ->get();

                foreach ($enOtrasVentas as $producto) {
                    $productoId = array_search($producto->serial, $series, true);

                    $validator->errors()->add(
                        "series.{$productoId}",
                        "Esta serie ya está asignada en la VENTA #{$producto->venta->consecutivo}.",
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['series.*' => 'serie'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['series.*.distinct' => 'Esta serie está repetida en la venta.'];
    }

    /**
     * Series ya validadas, con el id del producto como llave.
     *
     * @return array<int, string>
     */
    public function series(): array
    {
        $series = $this->input('series');

        if (! is_array($series)) {
            return [];
        }

        $limpias = [];

        foreach ($series as $productoId => $serie) {
            if (is_string($serie)) {
                $limpias[(int) $productoId] = $serie;
            }
        }

        return $limpias;
    }

    private function venta(): Venta
    {
        $venta = $this->route('venta');

        abort_unless($venta instanceof Venta, 404);

        return $venta;
    }
}
