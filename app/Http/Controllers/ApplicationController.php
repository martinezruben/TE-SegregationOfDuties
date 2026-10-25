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

        $crit = (string) $request->string('criticality');
        $status = (string) $request->string('status');
        $filters = [
            'search' => trim((string) $request->string('search')),
            'criticality' => in_array($crit, array_column(CriticalityLevel::cases(), 'value'), true) ? $crit : null,
            // id del owner, o "none" para aplicaciones sin owner.
            'owner' => trim((string) $request->string('owner')) ?: null,
            'status' => in_array($status, ['activo', 'inactivo'], true) ? $status : null,
        ];

        $aplicaciones = Application::query()
            ->with('owner:id,name')
            ->withCount('roles')
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('description', 'like', "%{$filters['search']}%")))
            ->when($filters['criticality'], fn ($q, $v) => $q->where('criticality_level', $v))
            ->when($filters['owner'], fn ($q, $v) => $v === 'none'
                ? $q->whereNull('owner_id')
                : $q->where('owner_id', (int) $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('is_active', $v === 'activo'))
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
            'filters' => $filters,
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
