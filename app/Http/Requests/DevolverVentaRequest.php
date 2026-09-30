<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
