<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Construye el árbol del menú lateral filtrado por permisos del usuario.
 * Un nodo se muestra sólo si el usuario tiene su permiso `ver`
 * (menus.permission_name); un agrupador se oculta si no tiene hijos visibles.
 * Ver context.md §8.6.
 */
class MenuBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(?User $user): array
    {
        $menus = Menu::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return $this->buildTree($menus, null, $user);
    }

    /**
     * @param  Collection<int, Menu>  $menus
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $menus, ?int $parentId, ?User $user): array
    {
        return $menus
            ->where('parent_id', $parentId)
            ->map(function (Menu $menu) use ($menus, $user): ?array {
                if (! $this->canView($menu, $user)) {
                    return null;
                }

                $children = $this->buildTree($menus, $menu->id, $user);

                // Un agrupador (sin ruta) sin hijos visibles se oculta.
                if (! $menu->route && $children === []) {
                    return null;
                }

                return [
                    'id' => $menu->id,
                    'label' => $menu->label,
                    'icon' => $menu->icon,
                    'route' => $menu->route,
                    'children' => $children,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function canView(Menu $menu, ?User $user): bool
    {
        if (! $menu->permission_name) {
            return true; // visible para cualquier autenticado
        }

        return $user !== null && $user->can($menu->permission_name);
    }
}
