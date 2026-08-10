<?php

namespace App\Http\Controllers;

use App\Http\Requests\AreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Area::class);

        $search = (string) $request->string('search');

        $areas = Area::query()
            ->when($search !== '', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Areas/Index', [
            'areas' => $areas,
            'filters' => ['search' => $search],
            'can' => [
                'create' => Gate::allows('create', Area::class),
                'update' => Gate::allows('update', new Area()),
                'delete' => Gate::allows('delete', new Area()),
            ],
        ]);
    }

    public function store(AreaRequest $request): RedirectResponse
    {
        $this->authorize('create', Area::class);

        Area::create($request->validated());

        return back()->with('success', 'Área creada correctamente.');
    }

    public function update(AreaRequest $request, Area $area): RedirectResponse
    {
        $this->authorize('update', $area);

        $area->update($request->validated());

        return back()->with('success', 'Área actualizada correctamente.');
    }

    public function destroy(Area $area): RedirectResponse
    {
        $this->authorize('delete', $area);

        $area->delete();

        return back()->with('success', 'Área eliminada correctamente.');
    }
}
