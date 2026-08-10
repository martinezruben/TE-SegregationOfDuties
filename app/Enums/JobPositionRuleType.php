<?php

namespace App\Enums;

enum JobPositionRuleType: string
{
    case Permitido = 'permitido';
    case NoPermitido = 'no_permitido';

    public function label(): string
    {
        return match ($this) {
            self::Permitido => 'Permitido',
            self::NoPermitido => 'No permitido',
        };
    }
}
