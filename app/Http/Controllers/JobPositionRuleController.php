<?php

namespace App\Http\Controllers;

use App\Enums\JobPositionRuleType;
use App\Models\Application;
use App\Models\JobPosition;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reglas de cargo ↔ rol (permitido / no permitido). Ver context.md §7.2.
 * Se gobiernan con los permisos cargos.* (editar la configuración del cargo).
 */
class JobPositionRuleController extends Controller
{
    public function index(JobPosition $cargo): Response
    {
        $this->authorize('view', $cargo);

        $cargo->load(['roleRules' => fn ($q) => $q->orderBy('name'), 'roleRules.application:id,name', 'genericPosition:id,name']);

        return Inertia::render('Cargos/Reglas', [
            'cargo' => [
                'id' => $cargo->id,
                'name' => $cargo->name,
                'is_generic' => $cargo->is_generic,
                'generic' => $cargo->genericPosition?->name,
            ],
            'reglas' => $cargo->roleRules->map(fn (Role $r) => [
                'role_id' => $r->id,
                'role_name' => $r->name,
                'application' => $r->application?->name,
                'rule_type' => $r->pivot->rule_type,
            ]),
            'aplicaciones' => Application::with('roles:id,application_id,name')->orderBy('name')->get(['id', 'name']),
            'tipos' => array_map(
                fn (JobPositionRuleType $t) => ['value' => $t->value, 'label' => $t->label()],
                JobPositionRuleType::cases(),
            ),
        ]);
    }

    public function store(Request $request, JobPosition $cargo): RedirectResponse
    {
        $this->authorize('update', $cargo);

        $data = $request->validate([
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'rule_type' => ['required', new Enum(JobPositionRuleType::class)],
        ]);

        // Upsert de la regla para (cargo, rol).
        $cargo->roleRules()->detach($data['role_id']);
        $cargo->roleRules()->attach($data['role_id'], ['rule_type' => $data['rule_type']]);

        return back()->with('success', 'Regla de cargo guardada.');
    }

    public function destroy(JobPosition $cargo, Role $role): RedirectResponse
    {
        $this->authorize('update', $cargo);

        $cargo->roleRules()->detach($role->id);

        return back()->with('success', 'Regla eliminada.');
    }
}
