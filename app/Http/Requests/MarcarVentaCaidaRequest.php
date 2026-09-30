<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarcarVentaCaidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marcarCaida', $this->route('venta'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo_caida' => ['required', 'string', 'max:1000'],
        ];
    }
}
