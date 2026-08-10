<?php

namespace App\Policies;

use App\Models\SodConflict;
use App\Models\User;

class SodConflictPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('matriz.ver');
    }

    public function view(User $user, SodConflict $sodConflict): bool
    {
        return $user->can('matriz.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('matriz.crear');
    }

    public function update(User $user, SodConflict $sodConflict): bool
    {
        return $user->can('matriz.editar');
    }

    public function delete(User $user, SodConflict $sodConflict): bool
    {
        return $user->can('matriz.eliminar');
    }
}
