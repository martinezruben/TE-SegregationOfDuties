<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Estructura del menú lateral jerárquico (context.md §8.6-8.7).
 * `permission_name` (tipo `ver`) controla la visibilidad de cada opción.
 *
 * Idempotente: usa updateOrCreate por (parent_id, label), de modo que puede
 * re-ejecutarse con seguridad para agregar/actualizar ítems sin duplicar.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;
        $make = function (array $attrs) use (&$order): Menu {
            $label = $attrs['label'];
            unset($attrs['label']);

            return Menu::updateOrCreate(
                ['parent_id' => null, 'label' => $label],
                array_merge(['order' => $order++, 'is_active' => true], $attrs),
            );
        };

        $childOf = function (Menu $parent) {
            $sub = 0;

            return function (array $attrs) use ($parent, &$sub): Menu {
                $label = $attrs['label'];
                unset($attrs['label']);

                return Menu::updateOrCreate(
                    ['parent_id' => $parent->id, 'label' => $label],
                    array_merge(['order' => $sub++, 'is_active' => true], $attrs),
                );
            };
        };

        $make([
            'label' => 'Dashboard', 'icon' => 'layout-dashboard',
            'route' => 'dashboard', 'permission_name' => 'dashboard.ver',
        ]);

        // Grupo: Catálogos
        $catalogos = $make(['label' => 'Catálogos', 'icon' => 'folder', 'permission_name' => null]);
        $child = $childOf($catalogos);
        $child(['label' => 'Áreas', 'icon' => 'map', 'route' => 'areas.index', 'permission_name' => 'areas.ver']);
        $child(['label' => 'Cargos', 'icon' => 'badge', 'route' => 'cargos.index', 'permission_name' => 'cargos.ver']);
        $child(['label' => 'Usuarios gestionados', 'icon' => 'users', 'route' => 'gestionados.index', 'permission_name' => 'gestionados.ver']);
        $child(['label' => 'Aplicaciones', 'icon' => 'app-window', 'route' => 'aplicaciones.index', 'permission_name' => 'aplicaciones.ver']);
        $child(['label' => 'Importar datos', 'icon' => 'upload', 'route' => 'importaciones.index', 'permission_name' => 'importaciones.ver']);

        // Grupo: SoD
        $sod = $make(['label' => 'Segregación (SoD)', 'icon' => 'shield-alert', 'permission_name' => null]);
        $child2 = $childOf($sod);
        $child2(['label' => 'Matriz de conflictos', 'icon' => 'grid-3x3', 'route' => 'matriz.index', 'permission_name' => 'matriz.ver']);
        $child2(['label' => 'Autorizadores', 'icon' => 'user-check', 'route' => 'autorizaciones.index', 'permission_name' => 'autorizaciones.ver']);
        $child2(['label' => 'Simulador', 'icon' => 'flask-conical', 'route' => 'simulador.index', 'permission_name' => 'simulador.ver']);
        $child2(['label' => 'Solicitudes', 'icon' => 'file-check', 'route' => 'solicitudes.index', 'permission_name' => 'solicitudes.ver']);
        $child2(['label' => 'Reporte de accesos', 'icon' => 'table-2', 'route' => 'reportes.accesos', 'permission_name' => 'reportes.ver']);
        $child2(['label' => 'Conflictos activos', 'icon' => 'shield-alert', 'route' => 'reportes.conflictos', 'permission_name' => 'reportes.ver']);
        $child2(['label' => 'Excepciones', 'icon' => 'file-warning', 'route' => 'reportes.excepciones', 'permission_name' => 'reportes.ver']);

        // Grupo: Administración
        $admin = $make(['label' => 'Administración', 'icon' => 'settings', 'permission_name' => null]);
        $child3 = $childOf($admin);
        $child3(['label' => 'Agentes', 'icon' => 'user-cog', 'route' => 'agentes.index', 'permission_name' => 'usuarios.ver']);
        $child3(['label' => 'Roles y permisos', 'icon' => 'key-round', 'route' => 'roles-permisos.index', 'permission_name' => 'roles.ver']);
        $child3(['label' => 'Menús', 'icon' => 'list-tree', 'route' => 'menus.index', 'permission_name' => 'menus.ver']);
        $child3(['label' => 'Bitácora', 'icon' => 'scroll-text', 'route' => 'bitacora.index', 'permission_name' => 'bitacora.ver']);
    }
}
