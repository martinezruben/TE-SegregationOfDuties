<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Pendiente = 'pendiente';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case Omitido = 'omitido';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
