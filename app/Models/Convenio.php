<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ConvenioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
     * Ventas en las que participa el convenio (con el valor que cubre en cada una).
     *
     * @return BelongsToMany<Venta, $this, ConvenioVenta, 'pivot'>
     */
    public function ventas(): BelongsToMany
    {
        return $this->belongsToMany(Venta::class)
            ->using(ConvenioVenta::class)
            ->withPivot(['id', 'valor', 'gasto_administrativo'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<Convenio>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true)->orderBy('nombre');
    }
}
