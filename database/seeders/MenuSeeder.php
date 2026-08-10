<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Estructura inicial del menú lateral jerárquico (context.md §8.6-8.7).
 * `permission_name` (tipo `ver`) controla la visibilidad de cada opción.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;
        $make = function (array $attrs) use (&$order): Menu {
            return Menu::create(array_merge(['order' => $order++, 'is_active' => true], $attrs));
        };

        $make([
            'label' => 'Dashboard', 'icon' => 'layout-dashboard',
            'route' => 'dashboard', 'permission_name' => 'dashboard.ver',
        ]);

        // Grupo: Catálogos
        $catalogos = $make(['label' => 'Catálogos', 'icon' => 'folder', 'permission_name' => null]);
        $sub = 0;
        $child = function (array $attrs) use (&$sub, $catalogos): Menu {
            return Menu::create(array_merge(
                ['parent_id' => $catalogos->id, 'order' => $sub++, 'is_active' => true],
                $attrs
            ));
        };
        $child(['label' => 'Áreas', 'icon' => 'map', 'route' => 'areas.index', 'permission_name' => 'areas.ver']);
        $child(['label' => 'Cargos', 'icon' => 'badge', 'route' => 'cargos.index', 'permission_name' => 'cargos.ver']);
        $child(['label' => 'Usuarios gestionados', 'icon' => 'users', 'route' => 'gestionados.index', 'permission_name' => 'gestionados.ver']);
        $child(['label' => 'Aplicaciones', 'icon' => 'app-window', 'route' => 'aplicaciones.index', 'permission_name' => 'aplicaciones.ver']);

        // Grupo: SoD
        $sod = $make(['label' => 'Segregación (SoD)', 'icon' => 'shield-alert', 'permission_name' => null]);
        $sub2 = 0;
        $child2 = function (array $attrs) use (&$sub2, $sod): Menu {
            return Menu::create(array_merge(
                ['parent_id' => $sod->id, 'order' => $sub2++, 'is_active' => true],
                $attrs
            ));
        };
        $child2(['label' => 'Matriz de conflictos', 'icon' => 'grid-3x3', 'route' => 'matriz.index', 'permission_name' => 'matriz.ver']);
        $child2(['label' => 'Simulador', 'icon' => 'flask-conical', 'route' => 'simulador.index', 'permission_name' => 'simulador.ver']);
        $child2(['label' => 'Solicitudes', 'icon' => 'file-check', 'route' => 'solicitudes.index', 'permission_name' => 'solicitudes.ver']);

        // Grupo: Administración
        $admin = $make(['label' => 'Administración', 'icon' => 'settings', 'permission_name' => null]);
        $sub3 = 0;
        $child3 = function (array $attrs) use (&$sub3, $admin): Menu {
            return Menu::create(array_merge(
                ['parent_id' => $admin->id, 'order' => $sub3++, 'is_active' => true],
                $attrs
            ));
        };
        $child3(['label' => 'Agentes', 'icon' => 'user-cog', 'route' => 'agentes.index', 'permission_name' => 'usuarios.ver']);
        $child3(['label' => 'Roles y permisos', 'icon' => 'key-round', 'route' => 'roles-permisos.index', 'permission_name' => 'roles.ver']);
        $child3(['label' => 'Menús', 'icon' => 'list-tree', 'route' => 'menus.index', 'permission_name' => 'menus.ver']);
        $child3(['label' => 'Bitácora', 'icon' => 'scroll-text', 'route' => 'bitacora.index', 'permission_name' => 'bitacora.ver']);
    }
}
