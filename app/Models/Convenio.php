<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ConvenioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property bool $requiere_gasto
 * @property bool $activo
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int|null $ventas_count
 */
class Convenio extends Model
{
    /** @use HasFactory<ConvenioFactory> */
    use HasFactory;

    protected $fillable = ['nombre', 'requiere_gasto', 'activo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requiere_gasto' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Venta, $this>
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * @param  Builder<Convenio>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true)->orderBy('nombre');
    }
}
