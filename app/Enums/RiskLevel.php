<?php

namespace App\Enums;

enum RiskLevel: string
{
    case Alto = 'alto';
    case Medio = 'medio';
    case Bajo = 'bajo';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Variante de Badge (shadcn/ui) para la UI. */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Alto => 'destructive',
            self::Medio => 'warning',
            self::Bajo => 'success',
        };
    }
}
