<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarConvenioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administrar');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activo' => ['sometimes', 'boolean'],
            'requiere_gasto' => ['sometimes', 'boolean'],
        ];
    }
}
