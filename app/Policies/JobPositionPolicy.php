<?php

namespace App\Policies;

use App\Models\JobPosition;
use App\Models\User;

class JobPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cargos.ver');
    }

    public function view(User $user, JobPosition $jobPosition): bool
    {
        return $user->can('cargos.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('cargos.crear');
    }

    public function update(User $user, JobPosition $jobPosition): bool
    {
        return $user->can('cargos.editar');
    }

    public function delete(User $user, JobPosition $jobPosition): bool
    {
        return $user->can('cargos.eliminar');
    }
}
