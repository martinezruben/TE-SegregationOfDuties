<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pendiente = 'pendiente';
    case EnAprobacion = 'en_aprobacion';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case Cancelado = 'cancelado';
    case AutoAprobado = 'auto_aprobado';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnAprobacion => 'En aprobación',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
            self::Cancelado => 'Cancelado',
            self::AutoAprobado => 'Auto-aprobado',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Aprobado, self::AutoAprobado => 'success',
            self::Rechazado, self::Cancelado => 'destructive',
            self::EnAprobacion => 'info',
            self::Pendiente => 'warning',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pendiente, self::EnAprobacion], true);
    }
}
