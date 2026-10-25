<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthorizationRuleRequest;
use App\Models\Application;
use App\Models\AuthorizationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuthorizationRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuthorizationRule::class);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'trigger_type' => $this->oneOf($request, 'trigger_type', ['sod_risk', 'application', 'cargo_misalignment']),
            'risk_level' => $this->oneOf($request, 'risk_level', ['alto', 'medio', 'bajo']),
            'application_id' => $request->integer('application_id') ?: null,
            // "supervisor" o el id de una persona fija en cualquiera de los pasos.
            'authorizer' => trim((string) $request->string('authorizer')) ?: null,
            'status' => $this->oneOf($request, 'status', ['activa', 'inactiva']),
        ];

        $reglas = AuthorizationRule::query()
            ->with(['application:id,name', 'steps.authorizer:id,name'])
            ->when($filters['search'] !== '', fn (Builder $q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
            ->when($filters['trigger_type'], fn (Builder $q, string $v) => $q->where('trigger_type', $v))
            ->when($filters['risk_level'], fn (Builder $q, string $v) => $q->where('risk_level', $v))
            ->when($filters['application_id'], fn (Builder $q, int $v) => $q->where('application_id', $v))
            ->when($filters['authorizer'], fn (Builder $q, string $v) => $q->whereHas('steps', fn (Builder $s) => $v === 'supervisor'
                ? $s->where('authorizer_type', 'supervisor')
                : $s->where('authorizer_type', 'fixed')->where('authorizer_id', (int) $v)))
            ->when($filters['status'], fn (Builder $q, string $v) => $q->where('is_active', $v === 'activa'))
            ->orderBy('trigger_type')
            ->orderBy('name')
            ->paginate(15)->withQueryString()
            ->through(fn (AuthorizationRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'trigger_type' => $r->trigger_type,
                'risk_level' => $r->risk_level,
                'application_id' => $r->application_id,
                'application' => $r->application?->name,
                'is_active' => $r->is_active,
                'steps' => $r->steps->map(fn ($s) => [
                    'authorizer_type' => $s->authorizer_type,
                    'authorizer_id' => $s->authorizer_id,
                    'authorizer' => $s->authorizer?->name,
                ])->values(),
            ]);

        return Inertia::render('Autorizaciones/Index', [
            'reglas' => $reglas,
            'filters' => $filters,
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

        DB::transaction(function () use ($request) {
            $rule = AuthorizationRule::create($request->safe()->except('steps'));
            $rule->syncSteps($request->validated('steps'));
        });

        return back()->with('success', 'Regla de autorización creada correctamente.');
    }

    public function update(AuthorizationRuleRequest $request, AuthorizationRule $autorizacion): RedirectResponse
    {
        $this->authorize('update', $autorizacion);

        DB::transaction(function () use ($request, $autorizacion) {
            $autorizacion->update($request->safe()->except('steps'));
            $autorizacion->syncSteps($request->validated('steps'));
        });

        return back()->with('success', 'Regla de autorización actualizada correctamente.');
    }

    public function destroy(AuthorizationRule $autorizacion): RedirectResponse
    {
        $this->authorize('delete', $autorizacion);

        $autorizacion->delete();

        return back()->with('success', 'Regla de autorización eliminada correctamente.');
    }

    /** @param list<string> $allowed */
    private function oneOf(Request $request, string $key, array $allowed): ?string
    {
        $v = (string) $request->string($key);

        return in_array($v, $allowed, true) ? $v : null;
    }
}
