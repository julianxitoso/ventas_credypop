<?php

namespace App\Enums;

enum FormaPagoInicial: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia electrónica',
        };
    }
}
