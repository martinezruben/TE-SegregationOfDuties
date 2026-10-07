<?php

namespace App\Services\Import;

use App\Models\Application;
use App\Models\ManagedUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa la "Foto": qué roles tiene cada usuario gestionado.
 * Solo agrega o actualiza vigencia; nunca quita accesos.
 *
 * Columnas: igg | aplicacion | rol | fecha_asignacion (opc) | expira (opc)
 */
class FotoImporter implements Importer
{
    public function kind(): string
    {
        return 'foto';
    }

    public function label(): string
    {
        return 'Foto de accesos (roles por usuario)';
    }

    public function permission(): string
    {
        return 'foto.editar';
    }

    public function template(): array
    {
        return [
            'headers' => ['igg', 'aplicacion', 'rol', 'fecha_asignacion', 'expira'],
            'example' => ['IGG001', 'ERP', 'Crear Proveedor', '2026-01-15', ''],
        ];
    }

    public function analyze(array $rows): array
    {
        $users = ManagedUser::all(['id', 'igg', 'name'])->keyBy(fn ($u) => Str::lower($u->igg));
        $apps = Application::all(['id', 'name'])->keyBy(fn ($a) => Str::lower($a->name));
        $roles = Role::all(['id', 'application_id', 'name'])
            ->keyBy(fn ($r) => $r->application_id.'|'.Str::lower($r->name));
        $existing = DB::table('user_role')->get(['managed_user_id', 'role_id', 'expires_at'])
            ->keyBy(fn ($r) => $r->managed_user_id.'|'.$r->role_id);

        $seen = [];
        $out = [];

        foreach ($rows as ['line' => $line, 'data' => $d]) {
            $igg = (string) SpreadsheetReader::pick($d, 'igg');
            $appName = (string) SpreadsheetReader::pick($d, 'aplicacion', 'app');
            $roleName = (string) SpreadsheetReader::pick($d, 'rol', 'role');
            $label = trim("{$igg} · {$appName} / {$roleName}", ' ·/');

            $err = fn (string $m) => ['line' => $line, 'status' => 'error', 'label' => $label, 'message' => $m, 'plan' => null];

            if ($igg === '' || $appName === '' || $roleName === '') {
                $out[] = $err('Faltan datos obligatorios (igg, aplicacion, rol).');

                continue;
            }

            $user = $users[Str::lower($igg)] ?? null;
            if (! $user) {
                $out[] = $err("Usuario gestionado con IGG «{$igg}» no existe.");

                continue;
            }
            $app = $apps[Str::lower($appName)] ?? null;
            if (! $app) {
                $out[] = $err("Aplicación «{$appName}» no existe.");

                continue;
            }
            $role = $roles[$app->id.'|'.Str::lower($roleName)] ?? null;
            if (! $role) {
                $out[] = $err("El rol «{$roleName}» no existe en la aplicación «{$app->name}».");

                continue;
            }

            $assigned = SpreadsheetReader::date(SpreadsheetReader::pick($d, 'fecha_asignacion', 'asignado'));
            $expires = SpreadsheetReader::date(SpreadsheetReader::pick($d, 'expira', 'vence', 'fecha_expiracion'));
            if ($assigned === false || $expires === false) {
                $out[] = $err('Fecha inválida (usa AAAA-MM-DD o DD/MM/AAAA).');

                continue;
            }
            if ($assigned && $expires && $expires->lt($assigned)) {
                $out[] = $err('La fecha de expiración es anterior a la de asignación.');

                continue;
            }

            $key = $user->id.'|'.$role->id;
            if (isset($seen[$key])) {
                $out[] = $err("Duplicado en el archivo (misma combinación que la fila {$seen[$key]}).");

                continue;
            }
            $seen[$key] = $line;

            $label = "{$user->name} ({$user->igg}) · {$app->name} / {$role->name}";
            $plan = [
                'managed_user_id' => $user->id,
                'role_id' => $role->id,
                'assigned_at' => $assigned?->toDateTimeString(),
                'expires_at' => $expires?->toDateTimeString(),
            ];

            $current = $existing[$key] ?? null;
            if ($current === null) {
                $out[] = ['line' => $line, 'status' => 'crear', 'label' => $label, 'message' => null, 'plan' => $plan];
            } elseif (($current->expires_at ? substr((string) $current->expires_at, 0, 10) : null) !== $expires?->toDateString()) {
                $out[] = ['line' => $line, 'status' => 'actualizar', 'label' => $label, 'message' => 'Cambia la fecha de expiración.', 'plan' => $plan];
            } else {
                $out[] = ['line' => $line, 'status' => 'sin_cambios', 'label' => $label, 'message' => 'Ya existe.', 'plan' => $plan];
            }
        }

        return $out;
    }

    public function apply(array $analysis, User $by): array
    {
        $res = ['creados' => 0, 'actualizados' => 0, 'sin_cambios' => 0];

        foreach ($analysis as $row) {
            $p = $row['plan'];
            match ($row['status']) {
                'crear' => $this->create($p, $by, $res),
                'actualizar' => $this->update($p, $res),
                'sin_cambios' => $res['sin_cambios']++,
                default => null,
            };
        }

        return $res;
    }

    private function create(array $p, User $by, array &$res): void
    {
        $user = ManagedUser::findOrFail($p['managed_user_id']);
        $user->roles()->attach($p['role_id'], [
            'assigned_at' => $p['assigned_at'] ?? now(),
            'assigned_by' => $by->id,
            'source' => 'import',
            'expires_at' => $p['expires_at'],
        ]);
        $res['creados']++;
    }

    private function update(array $p, array &$res): void
    {
        ManagedUser::findOrFail($p['managed_user_id'])
            ->roles()->updateExistingPivot($p['role_id'], ['expires_at' => $p['expires_at']]);
        $res['actualizados']++;
    }
}
