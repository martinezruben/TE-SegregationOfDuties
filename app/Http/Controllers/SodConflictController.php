<?php

namespace App\Http\Controllers;

use App\Enums\RiskLevel;
use App\Http\Requests\SodConflictRequest;
use App\Models\Application;
use App\Models\SodConflict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SodConflictController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SodConflict::class);

        $risk = (string) $request->string('risk_level');
        $status = (string) $request->string('status');
        $filters = [
            'search' => trim((string) $request->string('search')),
            'application_id' => $request->integer('application_id') ?: null,
            'risk_level' => in_array($risk, array_column(RiskLevel::cases(), 'value'), true) ? $risk : null,
            'status' => in_array($status, ['activo', 'inactivo'], true) ? $status : null,
        ];

        $conflictos = SodConflict::query()
            ->with(['roleA:id,name,application_id', 'roleA.application:id,name', 'roleB:id,name,application_id', 'roleB.application:id,name'])
            ->when($filters['search'] !== '', function (Builder $q) use ($filters) {
                $like = '%'.$filters['search'].'%';
                $role = fn (Builder $r) => $r->where('name', 'like', $like)
                    ->orWhereHas('application', fn (Builder $a) => $a->where('name', 'like', $like));

                $q->where(fn (Builder $w) => $w
                    ->where('description', 'like', $like)
                    ->orWhereHas('roleA', $role)
                    ->orWhereHas('roleB', $role));
            })
            // La aplicación puede estar en cualquiera de los dos roles del par.
            ->when($filters['application_id'], fn (Builder $q, int $id) => $q->where(fn (Builder $w) => $w
                ->whereHas('roleA', fn (Builder $r) => $r->where('application_id', $id))
                ->orWhereHas('roleB', fn (Builder $r) => $r->where('application_id', $id))))
            ->when($filters['risk_level'], fn (Builder $q, string $v) => $q->where('risk_level', $v))
            ->when($filters['status'], fn (Builder $q, string $v) => $q->where('is_active', $v === 'activo'))
            ->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return Inertia::render('Matriz/Index', [
            'conflictos' => $conflictos,
            'filters' => $filters,
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
