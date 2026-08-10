<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('menus.ver');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $user->can('menus.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('menus.crear');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $user->can('menus.editar');
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $user->can('menus.eliminar');
    }
}
