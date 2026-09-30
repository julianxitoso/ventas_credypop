<?php

namespace App\Models;

use App\Enums\EstadoVenta;
use Carbon\CarbonImmutable;
use Database\Factories\VentaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property int $asesor_id
 * @property string $cliente_nombre
 * @property string $cliente_cedula
 * @property string $cliente_celular
 * @property string|null $cliente_correo
 * @property string $articulo
 * @property string $marca
 * @property string $referencia
 * @property string $serial
 * @property int $valor_venta
 * @property int $valor_inicial
 * @property int $convenio_id
 * @property int|null $gasto_administrativo
 * @property string|null $observaciones
 * @property EstadoVenta $estado
 * @property string|null $numero_factura
 * @property CarbonImmutable|null $facturada_en
 * @property int|null $facturada_por
 * @property string|null $motivo_devolucion
 * @property CarbonImmutable|null $devuelta_en
 * @property int|null $devuelta_por
 * @property CarbonImmutable|null $corregida_en
 * @property string|null $motivo_caida
 * @property CarbonImmutable|null $caida_en
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $consecutivo
 * @property-read User $asesor
 * @property-read Convenio $convenio
 * @property-read User|null $facturador
 * @property-read User|null $devueltaPor
 */
class Venta extends Model
{
    /** @use HasFactory<VentaFactory> */
    use HasFactory;

    protected $table = 'ventas';

    // Solo lo que diligencia el asesor. El estado y la facturación
    // se cambian con acciones del facturador, nunca desde el formulario.
    protected $fillable = [
        'asesor_id',
        'cliente_nombre', 'cliente_cedula', 'cliente_celular', 'cliente_correo',
        'articulo', 'marca', 'referencia', 'serial',
        'valor_venta', 'valor_inicial', 'convenio_id', 'gasto_administrativo',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoVenta::class,
            'valor_venta' => 'integer',
            'valor_inicial' => 'integer',
            'gasto_administrativo' => 'integer',
            'facturada_en' => 'datetime',
            'devuelta_en' => 'datetime',
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
     * @return BelongsTo<Convenio, $this>
     */
    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
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
                ->orWhere('serial', 'like', "%{$termino}%");

            $numero = preg_replace('/\D/', '', $termino);

            if ($numero !== '' && strlen($numero) <= 18) {
                $query->orWhere('id', (int) $numero);
            }
        });
    }

    /**
     * Factura la venta solo si sigue pendiente.
     * Devuelve false si otro usuario ya la procesó.
     */
    public function facturar(string $numeroFactura, User $facturador): bool
    {
        return $this->actualizarSiEstaEn([EstadoVenta::Pendiente], [
            'estado' => EstadoVenta::Facturada,
            'numero_factura' => $numeroFactura,
            'facturada_en' => now(),
            'facturada_por' => $facturador->id,
        ]);
    }

    /**
     * Devuelve la venta al asesor solo si sigue pendiente.
     * Devuelve false si otro usuario ya la procesó.
     */
    public function devolver(string $motivo, User $facturador): bool
    {
        return $this->actualizarSiEstaEn([EstadoVenta::Pendiente], [
            'estado' => EstadoVenta::Devuelta,
            'motivo_devolucion' => $motivo,
            'devuelta_en' => now(),
            'devuelta_por' => $facturador->id,
        ]);
    }

    /**
     * El asesor corrige una venta devuelta y la envía de nuevo a facturación.
     * Se conserva el último motivo de devolución como referencia.
     *
     * @param  array<string, mixed>  $datos
     */
    public function corregir(array $datos): bool
    {
        return $this->actualizarSiEstaEn([EstadoVenta::Devuelta], [
            ...Arr::only($datos, $this->getFillable()),
            'asesor_id' => $this->asesor_id,
            'estado' => EstadoVenta::Pendiente,
            'corregida_en' => now(),
        ]);
    }

    /**
     * El asesor marca la venta como caída mientras no esté facturada.
     */
    public function marcarCaida(string $motivo): bool
    {
        return $this->actualizarSiEstaEn([EstadoVenta::Pendiente, EstadoVenta::Devuelta], [
            'estado' => EstadoVenta::Caida,
            'motivo_caida' => $motivo,
            'caida_en' => now(),
        ]);
    }

    public function sePuedeCorregir(): bool
    {
        return $this->estado === EstadoVenta::Devuelta;
    }

    public function sePuedeMarcarCaida(): bool
    {
        return in_array($this->estado, [EstadoVenta::Pendiente, EstadoVenta::Devuelta], true);
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
        $actualizada = static::query()
            ->whereKey($this->getKey())
            ->whereIn('estado', $estados)
            ->update($cambios) === 1;

        if ($actualizada) {
            $this->refresh();
        }

        return $actualizada;
    }
}
