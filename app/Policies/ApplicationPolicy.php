<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('aplicaciones.ver');
    }

    public function view(User $user, Application $application): bool
    {
        return $user->can('aplicaciones.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('aplicaciones.crear');
    }

    public function update(User $user, Application $application): bool
    {
        return $user->can('aplicaciones.editar');
    }

    public function delete(User $user, Application $application): bool
    {
        return $user->can('aplicaciones.eliminar');
    }
}
