<?php

namespace App\Http\Controllers;

use App\Http\Requests\MenuRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class MenuController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Menu::class);

        $menus = Menu::orderBy('parent_id')->orderBy('order')->get();
        $byId = $menus->keyBy('id');

        return Inertia::render('Menus/Index', [
            'menus' => $menus->map(fn (Menu $m) => [
                'id' => $m->id,
                'parent_id' => $m->parent_id,
                'parent' => $m->parent_id ? $byId[$m->parent_id]?->label : null,
                'label' => $m->label,
                'icon' => $m->icon,
                'route' => $m->route,
                'permission_name' => $m->permission_name,
                'order' => $m->order,
                'is_active' => $m->is_active,
            ]),
            'parents' => $menus->whereNull('parent_id')->values()->map(fn (Menu $m) => ['id' => $m->id, 'label' => $m->label]),
            'permissions' => Permission::orderBy('name')->pluck('name'),
            'can' => [
                'create' => Gate::allows('create', Menu::class),
                'update' => Gate::allows('update', new Menu()),
                'delete' => Gate::allows('delete', new Menu()),
            ],
        ]);
    }

    public function store(MenuRequest $request): RedirectResponse
    {
        $this->authorize('create', Menu::class);

        Menu::create($request->validated());

        return back()->with('success', 'Opción de menú creada.');
    }

    public function update(MenuRequest $request, Menu $menu): RedirectResponse
    {
        $this->authorize('update', $menu);

        $menu->update($request->validated());

        return back()->with('success', 'Opción de menú actualizada.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $this->authorize('delete', $menu);

        $menu->delete();

        return back()->with('success', 'Opción de menú eliminada.');
    }
}
