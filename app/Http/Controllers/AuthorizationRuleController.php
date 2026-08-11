<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthorizationRuleRequest;
use App\Models\Application;
use App\Models\AuthorizationRule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuthorizationRuleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', AuthorizationRule::class);

        $reglas = AuthorizationRule::query()
            ->with(['application:id,name', 'authorizer:id,name'])
            ->orderBy('trigger_type')
            ->orderBy('name')
            ->get()
            ->map(fn (AuthorizationRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'trigger_type' => $r->trigger_type,
                'risk_level' => $r->risk_level,
                'application_id' => $r->application_id,
                'application' => $r->application?->name,
                'authorizer_type' => $r->authorizer_type,
                'authorizer_id' => $r->authorizer_id,
                'authorizer' => $r->authorizer?->name,
                'is_active' => $r->is_active,
            ]);

        return Inertia::render('Autorizaciones/Index', [
            'reglas' => $reglas,
            'aplicaciones' => Application::orderBy('name')->get(['id', 'name']),
            'agentes' => User::orderBy('name')->get(['id', 'name']),
            'can' => [
                'create' => Gate::allows('create', AuthorizationRule::class),
                'update' => Gate::allows('update', new AuthorizationRule()),
                'delete' => Gate::allows('delete', new AuthorizationRule()),
            ],
        ]);
    }

    public function store(AuthorizationRuleRequest $request): RedirectResponse
    {
        $this->authorize('create', AuthorizationRule::class);

        AuthorizationRule::create($request->validated());

        return back()->with('success', 'Regla de autorización creada correctamente.');
    }

    public function update(AuthorizationRuleRequest $request, AuthorizationRule $autorizacion): RedirectResponse
    {
        $this->authorize('update', $autorizacion);

        $autorizacion->update($request->validated());

        return back()->with('success', 'Regla de autorización actualizada correctamente.');
    }

    public function destroy(AuthorizationRule $autorizacion): RedirectResponse
    {
        $this->authorize('delete', $autorizacion);

        $autorizacion->delete();

        return back()->with('success', 'Regla de autorización eliminada correctamente.');
    }
}
