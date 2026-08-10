<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Editor de roles de plataforma y sus permisos (RBAC). context.md §8.5.
 */
class PlatformRoleController extends Controller
{
    private const PROTECTED_ROLE = 'Administrador';

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('roles.ver'), 403);

        $permissions = Permission::orderBy('name')->pluck('name');

        // Agrupar permisos por recurso: { recurso: [acciones...] }.
        $groups = [];
        foreach ($permissions as $name) {
            [$resource, $action] = array_pad(explode('.', $name, 2), 2, '');
            $groups[$resource][] = ['name' => $name, 'action' => $action];
        }

        return Inertia::render('RolesPermisos/Index', [
            'roles' => Role::with('permissions:id,name')->orderBy('name')->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'protected' => $r->name === self::PROTECTED_ROLE,
                    'permissions' => $r->permissions->pluck('name'),
                ]),
            'groups' => collect($groups)->map(fn ($items, $resource) => [
                'resource' => $resource,
                'permissions' => $items,
            ])->values(),
            'canEdit' => (bool) $request->user()?->can('permisos.editar'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('permisos.editar'), 403);
        abort_if($role->name === self::PROTECTED_ROLE, 403, 'El rol Administrador no es editable.');

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('platform_permissions', 'name')],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        Artisan::call('permission:cache-reset');

        return back()->with('success', "Permisos de \"{$role->name}\" actualizados.");
    }
}
