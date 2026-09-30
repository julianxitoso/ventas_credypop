<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RestablecerContrasenaRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ];
    }
}
