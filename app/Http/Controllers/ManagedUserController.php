<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManagedUserRequest;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
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

        $search = (string) $request->string('search');

        $usuarios = ManagedUser::query()
            ->with(['area:id,name', 'jobPosition:id,name'])
            ->when($search !== '', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('external_id', 'like', "%{$search}%")
                ->orWhere('igg', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Gestionados/Index', [
            'usuarios' => $usuarios,
            'areas' => Area::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cargos' => JobPosition::orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search],
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
