<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Application;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Application $aplicacion): Response
    {
        $this->authorize('viewAny', Role::class);

        $aplicacion->load(['roles' => fn ($q) => $q->orderBy('name'), 'roles.options' => fn ($q) => $q->orderBy('name')]);

        return Inertia::render('Aplicaciones/Roles', [
            'aplicacion' => [
                'id' => $aplicacion->id,
                'name' => $aplicacion->name,
                'criticality_level' => $aplicacion->criticality_level,
            ],
            'roles' => $aplicacion->roles,
            'can' => [
                'create' => Gate::allows('create', Role::class),
                'update' => Gate::allows('update', new Role()),
                'delete' => Gate::allows('delete', new Role()),
            ],
        ]);
    }

    public function store(RoleRequest $request, Application $aplicacion): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $aplicacion->roles()->create($request->validated());

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $role->update($request->validated());

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $role->delete();

        return back()->with('success', 'Rol eliminado correctamente.');
    }
}
