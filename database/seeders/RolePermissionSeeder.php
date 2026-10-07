<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles y permisos RBAC de plataforma (spatie).
 * Convención de permisos: {recurso}.{acción}. Ver context.md §4.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Permisos por recurso.
     *
     * @var array<string, list<string>>
     */
    private array $matrix = [
        'dashboard' => ['ver'],
        'usuarios' => ['ver', 'crear', 'editar', 'eliminar'],
        'roles' => ['ver', 'crear', 'editar', 'eliminar'],
        'permisos' => ['ver', 'editar'],
        'menus' => ['ver', 'crear', 'editar', 'eliminar'],
        'areas' => ['ver', 'crear', 'editar', 'eliminar'],
        'cargos' => ['ver', 'crear', 'editar', 'eliminar'],
        'gestionados' => ['ver', 'crear', 'editar', 'eliminar'],
        'aplicaciones' => ['ver', 'crear', 'editar', 'eliminar'],
        'matriz' => ['ver', 'crear', 'editar', 'eliminar', 'definir'],
        'autorizaciones' => ['ver', 'crear', 'editar', 'eliminar'],
        'foto' => ['ver', 'editar'],
        'solicitudes' => ['ver', 'crear', 'autorizar', 'autorizar_mayor'],
        'simulador' => ['ver'],
        'reportes' => ['ver', 'exportar'],
        'bitacora' => ['ver'],
        'importaciones' => ['ver', 'crear'],
    ];

    public function run(): void
    {
        // Crear todos los permisos.
        $all = [];
        foreach ($this->matrix as $resource => $actions) {
            foreach ($actions as $action) {
                $name = "{$resource}.{$action}";
                Permission::findOrCreate($name, 'web');
                $all[] = $name;
            }
        }

        // Helpers para componer conjuntos de permisos.
        $only = fn (array $names) => $names;
        // Importaciones queda fuera de los roles de solo lectura: es una herramienta operativa.
        $allViews = array_values(array_filter($all, fn ($p) => str_ends_with($p, '.ver') && $p !== 'importaciones.ver'));
        $matrizAll = array_values(array_filter($all, fn ($p) => str_starts_with($p, 'matriz.')));
        $autorizacionesAll = array_values(array_filter($all, fn ($p) => str_starts_with($p, 'autorizaciones.')));

        $roles = [
            'Administrador' => $all,
            'Definidor de Matrices' => array_merge($matrizAll, $autorizacionesAll, ['aplicaciones.ver', 'roles.ver', 'dashboard.ver']),
            'Consultor' => $allViews,
            'Consultor + Autorizador' => array_merge($allViews, ['solicitudes.autorizar']),
            'Gestor de Seguridad' => array_merge($matrizAll, $autorizacionesAll, [
                'solicitudes.ver', 'solicitudes.autorizar', 'solicitudes.autorizar_mayor',
                'reportes.ver', 'reportes.exportar', 'bitacora.ver', 'dashboard.ver',
            ]),
            'Owner de Aplicación' => $only(['dashboard.ver', 'aplicaciones.ver', 'solicitudes.ver', 'solicitudes.autorizar']),
            'Jefe' => $only(['dashboard.ver', 'solicitudes.ver', 'solicitudes.autorizar']),
            'Solicitante' => $only(['dashboard.ver', 'simulador.ver', 'solicitudes.ver', 'solicitudes.crear']),
            'Auditor' => array_merge($allViews, ['reportes.exportar']),
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(array_values(array_unique($permissions)));
        }

        Artisan::call('permission:cache-reset');
    }
}
