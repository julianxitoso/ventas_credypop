<?php

namespace App\Http\Requests;

use App\Enums\FormaPagoInicial;
use App\Models\Convenio;
use App\Models\Venta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registro o corrección de una venta: datos del cliente, uno o más productos
 * (el total es la suma de sus valores), cuota inicial y uno o más convenios
 * cuyos valores suman el total menos la cuota inicial.
 */
class GuardarVentaRequest extends FormRequest
{
    private const MAXIMO_PRODUCTOS = 30;

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
     * Quita puntos, espacios y signos de los valores en pesos y deja la
     * inicial en 0 cuando no se escribe.
     */
    protected function prepareForValidation(): void
    {
        $productos = $this->input('productos');
        $convenios = $this->input('convenios');

        $this->merge([
            'valor_inicial' => $this->pesos($this->input('valor_inicial')) ?? 0,
            'productos' => is_array($productos)
                ? array_map(fn ($producto) => is_array($producto)
                    ? [...$producto, 'valor' => $this->pesos($producto['valor'] ?? null)]
                    : $producto, $productos)
                : $productos,
            'convenios' => is_array($convenios)
                ? array_map(fn ($convenio) => is_array($convenio)
                    ? [
                        ...$convenio,
                        'valor' => $this->pesos($convenio['valor'] ?? null),
                        'gasto_administrativo' => $this->pesos($convenio['gasto_administrativo'] ?? null),
                    ]
                    : $convenio, $convenios)
                : $convenios,
        ]);
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

            'productos' => ['required', 'array', 'min:1', 'max:'.self::MAXIMO_PRODUCTOS],
            'productos.*' => ['array'],
            // id de un producto ya existente (al corregir), para conservar su serie
            'productos.*.id' => ['nullable', 'integer'],
            'productos.*.articulo' => ['required', 'string', 'max:150'],
            'productos.*.marca' => ['required', 'string', 'max:80'],
            'productos.*.referencia' => ['required', 'string', 'max:80'],
            'productos.*.valor' => ['required', 'integer', 'min:1'],

            'valor_inicial' => ['required', 'integer', 'min:0'],
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

            'convenios' => ['required', 'array', 'min:1'],
            'convenios.*' => ['array'],
            'convenios.*.convenio_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('convenios', 'id')->where('activo', true),
            ],
            'convenios.*.valor' => ['required', 'integer', 'min:0'],
            'convenios.*.gasto_administrativo' => ['nullable', 'integer', 'min:1'],

            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Revisiones que miran la venta completa: inicial frente al total, suma de
     * los convenios y gasto administrativo de los convenios que lo requieren.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['productos', 'productos.*', 'convenios', 'convenios.*', 'valor_inicial'])) {
                    return;
                }

                $total = $this->total();
                $inicial = (int) $this->input('valor_inicial');

                if ($inicial > $total) {
                    $validator->errors()->add('valor_inicial', 'El valor de inicial no puede ser mayor que el total de la venta.');

                    return;
                }

                $esperado = $total - $inicial;
                $asignado = (int) collect($this->filasConvenios())->sum('valor');

                if ($asignado !== $esperado) {
                    $diferencia = $asignado < $esperado
                        ? 'faltan '.$this->formatoPesos($esperado - $asignado)
                        : 'sobran '.$this->formatoPesos($asignado - $esperado);

                    $validator->errors()->add(
                        'convenios',
                        'Los convenios deben sumar '.$this->formatoPesos($esperado).' (total menos la cuota inicial); '.$diferencia.'.',
                    );
                }

                foreach ($this->filasConvenios() as $posicion => $convenio) {
                    if ($this->conveniosElegidos()->get($convenio['convenio_id'])?->requiere_gasto && empty($convenio['gasto_administrativo'])) {
                        $validator->errors()->add(
                            "convenios.{$posicion}.gasto_administrativo",
                            'Este convenio requiere el gasto administrativo.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'productos.*.articulo' => 'artículo',
            'productos.*.marca' => 'marca',
            'productos.*.referencia' => 'referencia',
            'productos.*.valor' => 'valor del producto',
            'convenios.*.convenio_id' => 'convenio',
            'convenios.*.valor' => 'valor del convenio',
            'convenios.*.gasto_administrativo' => 'gasto administrativo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'productos.required' => 'Agrega al menos un producto.',
            'convenios.required' => 'Agrega al menos un convenio.',
            'convenios.*.convenio_id.distinct' => 'Este convenio está repetido.',
            'forma_pago_inicial.required' => 'Indica si la cuota inicial se pagó en efectivo o por transferencia.',
            'numero_transferencia.required' => 'Escribe el número de la transferencia.',
        ];
    }

    /**
     * Datos listos para guardar, separados en la venta, sus productos y sus
     * convenios. El gasto solo se conserva en los convenios que lo requieren,
     * la forma de pago solo si hay inicial y el número de transferencia solo
     * si se pagó por transferencia.
     *
     * @return array{
     *     venta: array<string, mixed>,
     *     productos: list<array{id: int|null, articulo: string, marca: string, referencia: string, valor: int}>,
     *     convenios: array<int, array{valor: int, gasto_administrativo: int|null}>
     * }
     */
    public function datosVenta(): array
    {
        $datos = $this->validated();

        $formaPago = $this->hayInicial() ? ($datos['forma_pago_inicial'] ?? null) : null;

        $productos = array_values(array_map(fn (array $producto): array => [
            'id' => isset($producto['id']) ? (int) $producto['id'] : null,
            'articulo' => $producto['articulo'],
            'marca' => $producto['marca'],
            'referencia' => $producto['referencia'],
            'valor' => (int) $producto['valor'],
        ], $datos['productos']));

        $convenios = [];

        foreach ($datos['convenios'] as $convenio) {
            $requiereGasto = (bool) $this->conveniosElegidos()->get($convenio['convenio_id'])?->requiere_gasto;

            $convenios[(int) $convenio['convenio_id']] = [
                'valor' => (int) $convenio['valor'],
                'gasto_administrativo' => $requiereGasto ? (int) $convenio['gasto_administrativo'] : null,
            ];
        }

        return [
            'venta' => [
                ...collect($datos)->only([
                    'cliente_nombre', 'cliente_cedula', 'cliente_celular', 'cliente_telefono',
                    'cliente_celular_2', 'cliente_correo', 'cliente_direccion', 'cliente_ciudad',
                    'observaciones',
                ])->all(),
                'valor_venta' => $this->total(),
                'valor_inicial' => (int) $datos['valor_inicial'],
                'forma_pago_inicial' => $formaPago,
                'numero_transferencia' => $formaPago === FormaPagoInicial::Transferencia->value
                    ? ($datos['numero_transferencia'] ?? null)
                    : null,
            ],
            'productos' => $productos,
            'convenios' => $convenios,
        ];
    }

    /**
     * Total de la venta: suma de los valores de los productos.
     */
    private function total(): int
    {
        return (int) collect($this->filasProductos())->sum('valor');
    }

    /**
     * Productos tal como llegaron del formulario (vacío si no llegó una lista).
     *
     * @return array<int, array<string, mixed>>
     */
    private function filasProductos(): array
    {
        $productos = $this->input('productos');

        return is_array($productos) ? array_filter($productos, is_array(...)) : [];
    }

    /**
     * Convenios tal como llegaron del formulario (vacío si no llegó una lista).
     *
     * @return array<int, array<string, mixed>>
     */
    private function filasConvenios(): array
    {
        $convenios = $this->input('convenios');

        return is_array($convenios) ? array_filter($convenios, is_array(...)) : [];
    }

    private function hayInicial(): bool
    {
        return (int) $this->input('valor_inicial') > 0;
    }

    /**
     * @return Collection<int, Convenio>
     */
    private function conveniosElegidos(): Collection
    {
        return once(fn (): Collection => Convenio::query()
            ->whereIn('id', collect($this->filasConvenios())->pluck('convenio_id')->filter()->all())
            ->get()
            ->keyBy('id'));
    }

    /**
     * "1250000" → "$1.250.000", como se ve en pantalla.
     */
    private function formatoPesos(int $valor): string
    {
        return '$'.Number::format($valor, locale: 'es_CO');
    }

    private function pesos(mixed $valor): ?string
    {
        if (is_int($valor)) {
            return (string) $valor;
        }

        if (! is_string($valor)) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $valor);

        return $digitos === '' ? null : $digitos;
    }
}
