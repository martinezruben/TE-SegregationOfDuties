<?php

namespace App\Policies;

use App\Models\Option;
use App\Models\User;

class OptionPolicy
{
    public function create(User $user): bool
    {
        return $user->can('aplicaciones.crear');
    }

    public function update(User $user, Option $option): bool
    {
        return $user->can('aplicaciones.editar');
    }

    public function delete(User $user, Option $option): bool
    {
        return $user->can('aplicaciones.eliminar');
    }
}
