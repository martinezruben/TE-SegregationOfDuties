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
];
