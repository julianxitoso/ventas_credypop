<?php

namespace App\Enums;

/**
 * Recorrido de una venta: el asesor la registra (pendiente), bodega asigna
 * las series (con serie), el facturador la factura (facturada) o la devuelve
 * a quien deba corregirla (devuelta). El asesor puede marcarla como caída
 * mientras no esté facturada.
 */
enum EstadoVenta: string
{
    case Pendiente = 'pendiente';
    case ConSerie = 'con_serie';
    case Facturada = 'facturada';
    case Devuelta = 'devuelta';
    case Caida = 'caida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::ConSerie => 'Pendiente con serie',
            self::Facturada => 'Facturada',
            self::Devuelta => 'Devuelta',
            self::Caida => 'Caída',
        };
    }

    /**
     * Estados que todavía esperan ser facturados (sin serie o con serie).
     *
     * @return list<self>
     */
    public static function porFacturar(): array
    {
        return [self::Pendiente, self::ConSerie];
    }
}
