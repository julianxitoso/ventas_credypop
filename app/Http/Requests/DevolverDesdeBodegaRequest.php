<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bodega devuelve una venta al asesor por un problema que no es de serie.
 */
class DevolverDesdeBodegaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('asignar-series');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo_devolucion' => ['required', 'string', 'max:1000'],
        ];
    }
}
