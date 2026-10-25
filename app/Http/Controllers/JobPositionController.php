<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobPositionRequest;
use App\Models\JobPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JobPositionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JobPosition::class);

        $type = (string) $request->string('type');
        $status = (string) $request->string('status');
        $filters = [
            'search' => trim((string) $request->string('search')),
            'type' => in_array($type, ['generico', 'especifico'], true) ? $type : null,
            'generic_position_id' => $request->integer('generic_position_id') ?: null,
            'status' => in_array($status, ['activo', 'inactivo'], true) ? $status : null,
        ];

        $cargos = JobPosition::query()
            ->with('genericPosition:id,name')
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('description', 'like', "%{$filters['search']}%")))
            ->when($filters['type'], fn ($q, $v) => $q->where('is_generic', $v === 'generico'))
            ->when($filters['generic_position_id'], fn ($q, $v) => $q->where('generic_position_id', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('is_active', $v === 'activo'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Cargos/Index', [
            'cargos' => $cargos,
            'genericos' => JobPosition::where('is_generic', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $filters,
            'can' => [
                'create' => Gate::allows('create', JobPosition::class),
                'update' => Gate::allows('update', new JobPosition()),
                'delete' => Gate::allows('delete', new JobPosition()),
            ],
        ]);
    }

    public function store(JobPositionRequest $request): RedirectResponse
    {
        $this->authorize('create', JobPosition::class);

        JobPosition::create($request->validated());

        return back()->with('success', 'Cargo creado correctamente.');
    }

    public function update(JobPositionRequest $request, JobPosition $cargo): RedirectResponse
    {
        $this->authorize('update', $cargo);

        $cargo->update($request->validated());

        return back()->with('success', 'Cargo actualizado correctamente.');
    }

    public function destroy(JobPosition $cargo): RedirectResponse
    {
        $this->authorize('delete', $cargo);

        $cargo->delete();

        return back()->with('success', 'Cargo eliminado correctamente.');
    }
}
