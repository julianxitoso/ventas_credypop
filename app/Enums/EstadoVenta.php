<?php

namespace App\Enums;

enum EstadoVenta: string
{
    case Pendiente = 'pendiente';
    case Facturada = 'facturada';
    case Devuelta = 'devuelta';
    case Caida = 'caida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Facturada => 'Facturada',
            self::Devuelta => 'Devuelta',
            self::Caida => 'Caída',
        };
    }
}
