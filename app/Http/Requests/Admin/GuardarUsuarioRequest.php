<?php

namespace App\Http\Requests\Admin;

use App\Enums\Rol;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuardarUsuarioRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'usuario' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && User::query()->whereRaw('lower(usuario) = ?', [Str::lower($value)])->exists()) {
                        $fail('Ese usuario ya existe.');
                    }
                },
            ],
            'rol' => ['required', Rule::enum(Rol::class)],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario.regex' => 'El usuario solo puede tener letras, números, punto, guion y guion bajo (sin espacios).',
        ];
    }
}
