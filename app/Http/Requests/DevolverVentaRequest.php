<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Devolución del facturador: motivo y tipo de error (serie, otros datos o ambos),
 * que define a quién le llega la venta para corregir.
 */
class DevolverVentaRequest extends FormRequest
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
            'motivo_devolucion' => ['required', 'string', 'max:1000'],
            'error_serie' => ['boolean'],
            'error_otros' => ['boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('error_serie') && ! $this->boolean('error_otros')) {
                    $validator->errors()->add('error_serie', 'Indica si el error es de la serie, de otros datos o de ambos.');
                }
            },
        ];
    }
}
