<?php

namespace App\Enums;

enum RequestType: string
{
    case Asignacion = 'asignacion';
    case Movimiento = 'movimiento';
    case ModificacionRol = 'modificacion_rol';

    public function label(): string
    {
        return match ($this) {
            self::Asignacion => 'Asignación de rol',
            self::Movimiento => 'Movimiento de roles',
            self::ModificacionRol => 'Modificación de rol',
        };
    }

    /** La modificación de un rol es una autorización mayor. */
    public function isMajor(): bool
    {
        return $this === self::ModificacionRol;
    }
}
