<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FacturarVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('facturar');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numero_factura' => ['required', 'string', 'max:50', 'unique:ventas,numero_factura'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_factura.unique' => 'Ese número de factura ya está registrado en otra venta.',
        ];
    }
}
