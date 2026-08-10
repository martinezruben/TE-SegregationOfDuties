<?php

namespace App\Http\Controllers;

use App\Enums\CriticalityLevel;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Application::class);

        $search = (string) $request->string('search');

        $aplicaciones = Application::query()
            ->with('owner:id,name')
            ->withCount('roles')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Aplicaciones/Index', [
            'aplicaciones' => $aplicaciones,
            'owners' => User::orderBy('name')->get(['id', 'name']),
            'criticidades' => array_map(
                fn (CriticalityLevel $c) => ['value' => $c->value, 'label' => $c->label()],
                CriticalityLevel::cases(),
            ),
            'filters' => ['search' => $search],
            'can' => [
                'create' => Gate::allows('create', Application::class),
                'update' => Gate::allows('update', new Application()),
                'delete' => Gate::allows('delete', new Application()),
            ],
        ]);
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $this->authorize('create', Application::class);

        Application::create($request->validated());

        return back()->with('success', 'Aplicación creada correctamente.');
    }

    public function update(ApplicationRequest $request, Application $aplicacion): RedirectResponse
    {
        $this->authorize('update', $aplicacion);

        $aplicacion->update($request->validated());

        return back()->with('success', 'Aplicación actualizada correctamente.');
    }

    public function destroy(Application $aplicacion): RedirectResponse
    {
        $this->authorize('delete', $aplicacion);

        $aplicacion->delete();

        return back()->with('success', 'Aplicación eliminada correctamente.');
    }
}
