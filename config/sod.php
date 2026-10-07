<?php

return [
    /*
    | Autenticación en dos pasos (context.md §9).
    | Los agentes con alguno de estos roles de plataforma DEBEN activar 2FA (TOTP)
    | antes de usar el resto del sistema. El resto puede activarlo voluntariamente.
    */
    'two_factor' => [
        'enforce' => (bool) env('TWO_FACTOR_ENFORCE', true),
        'required_roles' => ['Administrador', 'Gestor de Seguridad'],
    ],

    /*
    | SLA de aprobaciones (context.md §8.4.4 y §11).
    | Un paso pendiente más antiguo que `hours` se considera vencido y se le recuerda
    | al aprobador cada `reminder_every_hours`, hasta `max_reminders` veces.
    */
    'sla' => [
        'hours' => (int) env('SOD_SLA_HOURS', 48),
        'reminder_every_hours' => (int) env('SOD_SLA_REMINDER_EVERY_HOURS', 24),
        'max_reminders' => (int) env('SOD_SLA_MAX_REMINDERS', 5),
    ],
];
