<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venta;

class VentaPolicy
{
    /**
     * El asesor corrige sus propias ventas devueltas.
     */
    public function corregir(User $user, Venta $venta): bool
    {
        return $this->esDelAsesor($user, $venta) && $venta->sePuedeCorregir();
    }

    /**
     * El asesor marca como caídas sus propias ventas no facturadas.
     */
    public function marcarCaida(User $user, Venta $venta): bool
    {
        return $this->esDelAsesor($user, $venta) && $venta->sePuedeMarcarCaida();
    }

    private function esDelAsesor(User $user, Venta $venta): bool
    {
        return $user->esAsesor() && $venta->asesor_id === $user->id;
    }
}
