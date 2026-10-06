<?php

namespace App\Http\Requests;

use App\Enums\FormaPagoInicial;
use App\Models\Convenio;
use App\Models\Venta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarVentaRequest extends FormRequest
{
    private const CAMPOS_EN_PESOS = ['valor_venta', 'valor_inicial', 'gasto_administrativo'];

    /**
     * Registrar una venta nueva, o corregir una devuelta cuando la ruta trae la venta.
     */
    public function authorize(): bool
    {
        $venta = $this->route('venta');

        return $venta instanceof Venta
            ? $this->user()->can('corregir', $venta)
            : $this->user()->can('registrar-ventas');
    }

    /**
     * Quita espacios, puntos y signos de los valores en pesos y deja
     * la inicial en 0 cuando no se escribe.
     */
    protected function prepareForValidation(): void
    {
        $limpios = [];

        foreach (self::CAMPOS_EN_PESOS as $campo) {
            $valor = $this->input($campo);

            if (is_string($valor)) {
                $digitos = preg_replace('/\D/', '', $valor);
                $limpios[$campo] = $digitos === '' ? null : $digitos;
            }
        }

        $this->merge($limpios);

        if ($this->input('valor_inicial') === null) {
            $this->merge(['valor_inicial' => 0]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cliente_nombre' => ['required', 'string', 'max:150'],
            'cliente_cedula' => ['required', 'digits_between:5,12'],
            'cliente_celular' => ['required', 'digits:10'],
            'cliente_telefono' => ['required', 'digits_between:7,10'],
            'cliente_celular_2' => ['nullable', 'digits:10'],
            'cliente_correo' => ['required', 'email', 'max:150'],
            'cliente_direccion' => ['required', 'string', 'max:200'],
            'cliente_ciudad' => ['required', 'string', 'max:100'],
            'articulo' => ['required', 'string', 'max:150'],
            'marca' => ['required', 'string', 'max:80'],
            'referencia' => ['required', 'string', 'max:80'],
            'serial' => ['required', 'string', 'max:100'],
            'valor_venta' => ['required', 'integer', 'min:1'],
            'valor_inicial' => ['required', 'integer', 'min:0', 'lte:valor_venta'],
            'forma_pago_inicial' => [
                Rule::requiredIf(fn (): bool => $this->hayInicial()),
                'nullable',
                Rule::enum(FormaPagoInicial::class),
            ],
            'numero_transferencia' => [
                Rule::requiredIf(fn (): bool => $this->hayInicial()
                    && $this->input('forma_pago_inicial') === FormaPagoInicial::Transferencia->value),
                'nullable',
                'string',
                'max:50',
            ],
            'convenio_id' => ['required', 'integer', Rule::exists('convenios', 'id')->where('activo', true)],
            'gasto_administrativo' => [
                Rule::requiredIf(fn (): bool => $this->convenio()->requiere_gasto ?? false),
                'nullable',
                'integer',
                'min:1',
            ],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valor_inicial.lte' => 'El valor de inicial no puede ser mayor que el valor de venta.',
            'gasto_administrativo.required' => 'Este convenio requiere el gasto administrativo.',
            'forma_pago_inicial.required' => 'Indica si la cuota inicial se pagó en efectivo o por transferencia.',
            'numero_transferencia.required' => 'Escribe el número de la transferencia.',
        ];
    }

    /**
     * Datos listos para guardar: el gasto solo se conserva si el convenio lo
     * requiere, y la forma de pago solo si hay cuota inicial (el número de
     * transferencia, solo si se pagó por transferencia).
     *
     * @return array<string, mixed>
     */
    public function datosVenta(): array
    {
        $datos = $this->validated();

        if (! $this->convenio()->requiere_gasto) {
            $datos['gasto_administrativo'] = null;
        }

        $datos['forma_pago_inicial'] = $this->hayInicial() ? ($datos['forma_pago_inicial'] ?? null) : null;

        $datos['numero_transferencia'] = $datos['forma_pago_inicial'] === FormaPagoInicial::Transferencia->value
            ? ($datos['numero_transferencia'] ?? null)
            : null;

        return $datos;
    }

    private function hayInicial(): bool
    {
        return (int) $this->input('valor_inicial') > 0;
    }

    private function convenio(): ?Convenio
    {
        return once(fn (): ?Convenio => Convenio::find($this->integer('convenio_id')));
    }
}
