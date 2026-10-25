<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManagedUserRequest;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ManagedUserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ManagedUser::class);

        $status = (string) $request->string('status');
        $filters = [
            'search' => trim((string) $request->string('search')),
            'area_id' => $request->integer('area_id') ?: null,
            'job_position_id' => $request->integer('job_position_id') ?: null,
            // id de un supervisor, o "none" para quienes no tienen jefe asignado.
            'supervisor' => trim((string) $request->string('supervisor')) ?: null,
            'status' => in_array($status, ['activo', 'inactivo'], true) ? $status : null,
        ];

        $usuarios = ManagedUser::query()
            ->with(['area:id,name', 'jobPosition:id,name', 'supervisor:id,name'])
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('external_id', 'like', "%{$filters['search']}%")
                ->orWhere('igg', 'like', "%{$filters['search']}%")))
            ->when($filters['area_id'], fn ($q, $v) => $q->where('area_id', $v))
            ->when($filters['job_position_id'], fn ($q, $v) => $q->where('job_position_id', $v))
            ->when($filters['supervisor'], fn ($q, $v) => $v === 'none'
                ? $q->whereNull('supervisor_id')
                : $q->where('supervisor_id', (int) $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('is_active', $v === 'activo'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Gestionados/Index', [
            'usuarios' => $usuarios,
            'areas' => Area::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cargos' => JobPosition::orderBy('name')->get(['id', 'name']),
            'supervisores' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'can' => [
                'create' => Gate::allows('create', ManagedUser::class),
                'update' => Gate::allows('update', new ManagedUser()),
                'delete' => Gate::allows('delete', new ManagedUser()),
            ],
        ]);
    }

    public function store(ManagedUserRequest $request): RedirectResponse
    {
        $this->authorize('create', ManagedUser::class);

        ManagedUser::create($request->validated());

        return back()->with('success', 'Usuario gestionado creado correctamente.');
    }

    public function update(ManagedUserRequest $request, ManagedUser $gestionado): RedirectResponse
    {
        $this->authorize('update', $gestionado);

        $gestionado->update($request->validated());

        return back()->with('success', 'Usuario gestionado actualizado correctamente.');
    }

    public function destroy(ManagedUser $gestionado): RedirectResponse
    {
        $this->authorize('delete', $gestionado);

        $gestionado->delete();

        return back()->with('success', 'Usuario gestionado eliminado correctamente.');
    }
}
