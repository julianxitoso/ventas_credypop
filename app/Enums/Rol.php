<?php

namespace App\Enums;

enum Rol: string
{
    case Asesor = 'asesor';
    case Bodega = 'bodega';
    case Facturador = 'facturador';
    case Gerente = 'gerente';
    case Admin = 'admin';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Asesor => 'Asesor',
            self::Bodega => 'Bodega',
            self::Facturador => 'Facturador',
            self::Gerente => 'Gerente',
            self::Admin => 'Administrador',
        };
    }
}
