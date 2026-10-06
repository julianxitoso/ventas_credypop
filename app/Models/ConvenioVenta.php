<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Un convenio dentro de una venta, con el valor que cubre y su gasto administrativo.
 *
 * @property int $id
 * @property int $venta_id
 * @property int $convenio_id
 * @property int $valor
 * @property int|null $gasto_administrativo
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Convenio $convenio
 * @property-read Venta $venta
 */
class ConvenioVenta extends Pivot
{
    protected $table = 'convenio_venta';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'integer',
            'gasto_administrativo' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Convenio, $this>
     */
    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    /**
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
