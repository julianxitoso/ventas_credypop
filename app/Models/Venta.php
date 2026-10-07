<?php

namespace App\Models;

use App\Enums\EstadoVenta;
use App\Enums\FormaPagoInicial;
use App\Enums\ResponsableDevolucion;
use Carbon\CarbonImmutable;
use Closure;
use Database\Factories\VentaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $asesor_id
 * @property string $cliente_nombre
 * @property string $cliente_cedula
 * @property string $cliente_celular
 * @property string|null $cliente_telefono
 * @property string|null $cliente_celular_2
 * @property string|null $cliente_correo
 * @property string|null $cliente_direccion
 * @property string|null $cliente_ciudad
 * @property int $valor_venta Total de la venta: suma de los productos.
 * @property int $valor_inicial
 * @property FormaPagoInicial|null $forma_pago_inicial
 * @property string|null $numero_transferencia
 * @property string|null $observaciones
 * @property EstadoVenta $estado
 * @property CarbonImmutable|null $series_asignadas_en
 * @property int|null $series_asignadas_por
 * @property CarbonImmutable|null $con_serie_en Última vez que quedó lista para facturar.
 * @property string|null $numero_factura
 * @property CarbonImmutable|null $facturada_en
 * @property int|null $facturada_por
 * @property string|null $motivo_devolucion
 * @property CarbonImmutable|null $devuelta_en
 * @property int|null $devuelta_por
 * @property ResponsableDevolucion|null $devuelta_a Quién debe corregir la devolución.
 * @property bool $devolucion_por_serie
 * @property bool $devolucion_por_otros
 * @property bool $revisar_serie Bodega debe revisar las series aunque ya estén puestas.
 * @property CarbonImmutable|null $corregida_en
 * @property string|null $nota_correccion
 * @property string|null $motivo_caida
 * @property CarbonImmutable|null $caida_en
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $consecutivo
 * @property-read User $asesor
 * @property-read Collection<int, VentaProducto> $productos
 * @property-read Collection<int, Convenio> $convenios
 * @property-read Collection<int, ConvenioVenta> $conveniosVenta
 * @property-read User|null $facturador
 * @property-read User|null $devueltaPor
 * @property-read User|null $seriesAsignadasPor
 */
class Venta extends Model
{
    /** @use HasFactory<VentaFactory> */
    use HasFactory;

    protected $table = 'ventas';

    // Solo lo que diligencia el asesor. El estado, las series y la facturación
    // se cambian con las acciones de bodega y del facturador, nunca desde el formulario.
    protected $fillable = [
        'asesor_id',
        'cliente_nombre', 'cliente_cedula', 'cliente_celular', 'cliente_telefono', 'cliente_celular_2',
        'cliente_correo', 'cliente_direccion', 'cliente_ciudad',
        'valor_venta', 'valor_inicial', 'forma_pago_inicial', 'numero_transferencia',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoVenta::class,
            'forma_pago_inicial' => FormaPagoInicial::class,
            'valor_venta' => 'integer',
            'valor_inicial' => 'integer',
            'series_asignadas_en' => 'datetime',
            'con_serie_en' => 'datetime',
            'facturada_en' => 'datetime',
            'devuelta_en' => 'datetime',
            'devuelta_a' => ResponsableDevolucion::class,
            'devolucion_por_serie' => 'boolean',
            'devolucion_por_otros' => 'boolean',
            'revisar_serie' => 'boolean',
            'corregida_en' => 'datetime',
            'caida_en' => 'datetime',
        ];
    }

    /**
     * Número de la venta con ceros a la izquierda: 125 → "000125".
     *
     * @return Attribute<string, never>
     */
    protected function consecutivo(): Attribute
    {
        return Attribute::get($this->numeroConsecutivo(...));
    }

    private function numeroConsecutivo(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    /**
     * @return HasMany<VentaProducto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(VentaProducto::class)->orderBy('id');
    }

    /**
     * Convenios de la venta. Para leer el valor y el gasto de cada uno, usar
     * conveniosVenta(); esta relación sirve para asignarlos con sync().
     *
     * @return BelongsToMany<Convenio, $this, ConvenioVenta, 'pivot'>
     */
    public function convenios(): BelongsToMany
    {
        return $this->belongsToMany(Convenio::class)
            ->using(ConvenioVenta::class)
            ->withPivot(['id', 'valor', 'gasto_administrativo'])
            ->withTimestamps()
            ->orderByPivot('id');
    }

    /**
     * Cada convenio de la venta con el valor que cubre y su gasto administrativo.
     *
     * @return HasMany<ConvenioVenta, $this>
     */
    public function conveniosVenta(): HasMany
    {
        return $this->hasMany(ConvenioVenta::class)->orderBy('id');
    }

    /**
     * Texto corto de los productos para listados: "Televisor (+2 más)".
     */
    public function resumenProductos(): string
    {
        $primero = $this->productos->first();

        if ($primero === null) {
            return '—';
        }

        $otros = $this->productos->count() - 1;

        return $otros > 0 ? "{$primero->articulo} (+{$otros} más)" : $primero->articulo;
    }

    /**
     * Guarda los productos y los convenios de la venta. Los productos que
     * traen el id de uno existente se actualizan conservando su serie (la
     * asigna bodega); los demás se crean sin serie y los que no vienen se borran.
     *
     * @param  list<array{id?: int|null, articulo: string, marca: string, referencia: string, valor: int}>  $productos
     * @param  array<int, array{valor: int, gasto_administrativo: int|null}>  $convenios  Llave: id del convenio.
     */
    public function guardarDetalle(array $productos, array $convenios): void
    {
        $existentes = $this->productos()->get()->keyBy('id');
        $conservados = [];

        foreach ($productos as $producto) {
            $datos = Arr::only($producto, ['articulo', 'marca', 'referencia', 'valor']);
            $existente = $existentes->get($producto['id'] ?? 0);

            if ($existente !== null) {
                $existente->update($datos);
                $conservados[] = $existente->id;
            } else {
                $conservados[] = $this->productos()->create($datos)->id;
            }
        }

        $this->productos()->whereNotIn('id', $conservados)->delete();
        $this->convenios()->sync($convenios);
    }

    /**
     * Si todos los productos tienen serie asignada.
     */
    public function tieneTodasLasSeries(): bool
    {
        return ! $this->productos()
            ->where(fn (Builder $query) => $query->whereNull('serial')->orWhere('serial', ''))
            ->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function facturador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facturada_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function devueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'devuelta_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seriesAsignadasPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'series_asignadas_por');
    }

    /**
     * Ventas que bodega debe trabajar: pendientes de serie y devueltas a bodega.
     *
     * @param  Builder<Venta>  $query
     */
    public function scopeACargoDeBodega(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('estado', EstadoVenta::Pendiente)
            ->orWhere(fn (Builder $devuelta) => $devuelta
                ->where('estado', EstadoVenta::Devuelta)
                ->where('devuelta_a', ResponsableDevolucion::Bodega)));
    }

    /**
     * Busca por nombre del cliente, cédula, serial o número de venta
     * ("125", "000125" o "VENTA #000125").
     *
     * @param  Builder<Venta>  $query
     */
    public function scopeBuscar(Builder $query, ?string $termino): void
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return;
        }

        $query->where(function (Builder $query) use ($termino) {
            $query->where('cliente_nombre', 'like', "%{$termino}%")
                ->orWhere('cliente_cedula', 'like', "{$termino}%")
                ->orWhereHas('productos', fn (Builder $producto) => $producto->where('serial', 'like', "%{$termino}%"));

            $numero = preg_replace('/\D/', '', $termino);

            if ($numero !== '' && strlen($numero) <= 18) {
                $query->orWhere('id', (int) $numero);
            }
        });
    }

    /**
     * Bodega asigna la serie de cada producto y la venta queda lista para
     * facturar. Solo si está a cargo de bodega (pendiente o devuelta a bodega).
     *
     * @param  array<int, string>  $series  Llave: id del producto.
     */
    public function asignarSeries(array $series, User $bodega): bool
    {
        return DB::transaction(function () use ($series, $bodega): bool {
            $actualizada = $this->actualizarSi(
                fn (Builder $query) => $query->aCargoDeBodega(),
                [
                    'estado' => EstadoVenta::ConSerie,
                    'series_asignadas_en' => now(),
                    'series_asignadas_por' => $bodega->id,
                    'con_serie_en' => now(),
                    'revisar_serie' => false,
                ],
            );

            if ($actualizada) {
                foreach ($this->productos()->get() as $producto) {
                    $producto->update(['serial' => $series[$producto->id]]);
                }
            }

            return $actualizada;
        });
    }

    /**
     * Bodega devuelve la venta al asesor por un problema que no es de serie.
     * Cuando el asesor la corrija, vuelve a bodega (revisar_serie).
     */
    public function devolverDesdeBodega(string $motivo, User $bodega): bool
    {
        return $this->actualizarSi(
            fn (Builder $query) => $query->aCargoDeBodega(),
            [
                'estado' => EstadoVenta::Devuelta,
                'devuelta_a' => ResponsableDevolucion::Asesor,
                'devolucion_por_serie' => false,
                'devolucion_por_otros' => true,
                'revisar_serie' => true,
                'motivo_devolucion' => $motivo,
                'devuelta_en' => now(),
                'devuelta_por' => $bodega->id,
            ],
        );
    }

    /**
     * Factura la venta solo si ya tiene serie (pendiente con serie).
     * Devuelve false si otro usuario ya la procesó.
     */
    public function facturar(string $numeroFactura, User $facturador): bool
    {
        return $this->actualizarSiEstaEn([EstadoVenta::ConSerie], [
            'estado' => EstadoVenta::Facturada,
            'numero_factura' => $numeroFactura,
            'facturada_en' => now(),
            'facturada_por' => $facturador->id,
        ]);
    }

    /**
     * El facturador devuelve una venta con serie según el tipo de error:
     * solo otros datos → asesor; solo serie → bodega; ambos → primero el
     * asesor y después bodega (revisar_serie).
     */
    public function devolver(string $motivo, bool $porSerie, bool $porOtros, User $facturador): bool
    {
        return $this->actualizarSiEstaEn([EstadoVenta::ConSerie], [
            'estado' => EstadoVenta::Devuelta,
            'devuelta_a' => $porOtros ? ResponsableDevolucion::Asesor : ResponsableDevolucion::Bodega,
            'devolucion_por_serie' => $porSerie,
            'devolucion_por_otros' => $porOtros,
            'revisar_serie' => $porSerie && $porOtros,
            'motivo_devolucion' => $motivo,
            'devuelta_en' => now(),
            'devuelta_por' => $facturador->id,
        ]);
    }

    /**
     * El asesor corrige una venta que le devolvieron (datos, productos y
     * convenios). Si todos los productos conservan su serie y no hay que
     * revisarla, vuelve directo al facturador; si no, pasa por bodega.
     * Se conserva el último motivo de devolución junto con la nota del asesor
     * sobre lo que corrigió. Todo o nada.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array{id?: int|null, articulo: string, marca: string, referencia: string, valor: int}>  $productos
     * @param  array<int, array{valor: int, gasto_administrativo: int|null}>  $convenios
     */
    public function corregir(array $datos, array $productos, array $convenios, string $notaCorreccion): bool
    {
        return DB::transaction(function () use ($datos, $productos, $convenios, $notaCorreccion): bool {
            $actualizada = $this->actualizarSi(
                fn (Builder $query) => $query
                    ->where('estado', EstadoVenta::Devuelta)
                    ->where('devuelta_a', ResponsableDevolucion::Asesor),
                [
                    ...Arr::only($datos, $this->getFillable()),
                    'asesor_id' => $this->asesor_id,
                    'estado' => EstadoVenta::Pendiente,
                    'corregida_en' => now(),
                    'nota_correccion' => $notaCorreccion,
                ],
            );

            if (! $actualizada) {
                return false;
            }

            $this->guardarDetalle($productos, $convenios);

            if (! $this->revisar_serie && $this->tieneTodasLasSeries()) {
                $this->forceFill(['estado' => EstadoVenta::ConSerie, 'con_serie_en' => now()])->save();
            }

            return true;
        });
    }

    /**
     * El asesor marca la venta como caída mientras no esté facturada.
     */
    public function marcarCaida(string $motivo): bool
    {
        return $this->actualizarSiEstaEn([EstadoVenta::Pendiente, EstadoVenta::ConSerie, EstadoVenta::Devuelta], [
            'estado' => EstadoVenta::Caida,
            'motivo_caida' => $motivo,
            'caida_en' => now(),
        ]);
    }

    /**
     * El asesor solo corrige las devoluciones que le corresponden a él.
     */
    public function sePuedeCorregir(): bool
    {
        return $this->estado === EstadoVenta::Devuelta
            && $this->devuelta_a === ResponsableDevolucion::Asesor;
    }

    public function sePuedeMarcarCaida(): bool
    {
        return in_array($this->estado, [EstadoVenta::Pendiente, EstadoVenta::ConSerie, EstadoVenta::Devuelta], true);
    }

    public function estaACargoDeBodega(): bool
    {
        return $this->estado === EstadoVenta::Pendiente
            || ($this->estado === EstadoVenta::Devuelta && $this->devuelta_a === ResponsableDevolucion::Bodega);
    }

    /**
     * Actualiza la venta solo si sigue en alguno de los estados indicados,
     * para que dos usuarios no la procesen al mismo tiempo.
     *
     * @param  list<EstadoVenta>  $estados
     * @param  array<string, mixed>  $cambios
     */
    private function actualizarSiEstaEn(array $estados, array $cambios): bool
    {
        return $this->actualizarSi(fn (Builder $query) => $query->whereIn('estado', $estados), $cambios);
    }

    /**
     * Actualiza la venta solo si todavía cumple la condición.
     *
     * @param  Closure(Builder<Venta>): mixed  $condicion
     * @param  array<string, mixed>  $cambios
     */
    private function actualizarSi(Closure $condicion, array $cambios): bool
    {
        $consulta = Venta::query()->whereKey($this->getKey());
        $condicion($consulta);

        $actualizada = $consulta->update($cambios) === 1;

        if ($actualizada) {
            $this->refresh();
        }

        return $actualizada;
    }
}
