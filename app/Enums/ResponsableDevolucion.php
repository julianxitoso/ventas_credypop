<?php

namespace App\Enums;

/**
 * Quién debe corregir una venta devuelta.
 */
enum ResponsableDevolucion: string
{
    case Asesor = 'asesor';
    case Bodega = 'bodega';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Asesor => 'Asesor',
            self::Bodega => 'Bodega',
        };
    }
}
