<?php

namespace App\Http\Controllers;

use App\Enums\RiskLevel;
use App\Http\Requests\SodConflictRequest;
use App\Models\Application;
use App\Models\SodConflict;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SodConflictController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', SodConflict::class);

        $conflictos = SodConflict::query()
            ->with(['roleA:id,name,application_id', 'roleA.application:id,name', 'roleB:id,name,application_id', 'roleB.application:id,name'])
            ->orderByDesc('id')
            ->paginate(10);

        return Inertia::render('Matriz/Index', [
            'conflictos' => $conflictos,
            'aplicaciones' => Application::query()
                ->with('roles:id,application_id,name')
                ->orderBy('name')
                ->get(['id', 'name']),
            'riesgos' => array_map(
                fn (RiskLevel $r) => ['value' => $r->value, 'label' => $r->label()],
                RiskLevel::cases(),
            ),
            'can' => [
                'create' => Gate::allows('create', SodConflict::class),
                'update' => Gate::allows('update', new SodConflict()),
                'delete' => Gate::allows('delete', new SodConflict()),
            ],
        ]);
    }

    public function store(SodConflictRequest $request): RedirectResponse
    {
        $this->authorize('create', SodConflict::class);

        SodConflict::create($request->validated());

        return back()->with('success', 'Conflicto agregado a la matriz.');
    }

    public function update(SodConflictRequest $request, SodConflict $matriz): RedirectResponse
    {
        $this->authorize('update', $matriz);

        $matriz->update($request->validated());

        return back()->with('success', 'Conflicto actualizado.');
    }

    public function destroy(SodConflict $matriz): RedirectResponse
    {
        $this->authorize('delete', $matriz);

        $matriz->delete();

        return back()->with('success', 'Conflicto eliminado de la matriz.');
    }
}
