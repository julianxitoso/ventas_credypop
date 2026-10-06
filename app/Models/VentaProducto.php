<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\VentaProductoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un producto (línea) de una venta.
 *
 * @property int $id
 * @property int $venta_id
 * @property string $articulo
 * @property string $marca
 * @property string $referencia
 * @property string|null $serial
 * @property int $valor
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Venta $venta
 */
class VentaProducto extends Model
{
    /** @use HasFactory<VentaProductoFactory> */
    use HasFactory;

    protected $fillable = ['articulo', 'marca', 'referencia', 'serial', 'valor'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
