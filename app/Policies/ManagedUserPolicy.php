<?php

namespace App\Policies;

use App\Models\ManagedUser;
use App\Models\User;

class ManagedUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('gestionados.ver');
    }

    public function view(User $user, ManagedUser $managedUser): bool
    {
        return $user->can('gestionados.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('gestionados.crear');
    }

    public function update(User $user, ManagedUser $managedUser): bool
    {
        return $user->can('gestionados.editar');
    }

    public function delete(User $user, ManagedUser $managedUser): bool
    {
        return $user->can('gestionados.eliminar');
    }
}
