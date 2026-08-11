<?php

namespace App\Policies;

use App\Models\AuthorizationRule;
use App\Models\User;

class AuthorizationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('autorizaciones.ver');
    }

    public function view(User $user, AuthorizationRule $rule): bool
    {
        return $user->can('autorizaciones.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('autorizaciones.crear');
    }

    public function update(User $user, AuthorizationRule $rule): bool
    {
        return $user->can('autorizaciones.editar');
    }

    public function delete(User $user, AuthorizationRule $rule): bool
    {
        return $user->can('autorizaciones.eliminar');
    }
}
