<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Roles de NEGOCIO (parte del catálogo de aplicaciones).
 * Se gobiernan con los permisos de aplicaciones.*.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('aplicaciones.ver');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('aplicaciones.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('aplicaciones.crear');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('aplicaciones.editar');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('aplicaciones.eliminar');
    }
}
