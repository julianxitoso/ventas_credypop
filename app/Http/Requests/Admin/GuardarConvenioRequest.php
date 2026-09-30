<?php

namespace App\Http\Requests\Admin;

use App\Models\Convenio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class GuardarConvenioRequest extends FormRequest
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
            'nombre' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && Convenio::query()->whereRaw('lower(nombre) = ?', [Str::lower($value)])->exists()) {
                        $fail('Ya existe un convenio con ese nombre.');
                    }
                },
            ],
            'requiere_gasto' => ['boolean'],
        ];
    }
}
