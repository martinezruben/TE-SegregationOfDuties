<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ManagedUser;
use App\Models\Role;
use App\Services\SodEvaluator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SimulatorController extends Controller
{
    public function index(Request $request, SodEvaluator $evaluator): Response
    {
        abort_unless((bool) $request->user()?->can('simulador.ver'), 403);

        $validated = $request->validate([
            'managed_user_id' => ['nullable', 'exists:managed_users,id'],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $evaluation = null;
        $target = null;

        if (! empty($validated['managed_user_id']) && ! empty($validated['role_id'])) {
            $user = ManagedUser::with('jobPosition')->findOrFail($validated['managed_user_id']);
            $role = Role::with('application:id,name')->findOrFail($validated['role_id']);
            $evaluation = $evaluator->evaluate($user, $role);
            $target = [
                'user' => ['id' => $user->id, 'name' => $user->name, 'igg' => $user->igg],
                'role' => ['id' => $role->id, 'name' => $role->name, 'application' => $role->application?->name],
            ];
        }

        return Inertia::render('Simulador/Index', [
            'usuarios' => ManagedUser::where('is_active', true)->orderBy('name')->get(['id', 'name', 'igg']),
            'aplicaciones' => Application::with('roles:id,application_id,name')->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'managed_user_id' => $validated['managed_user_id'] ?? null,
                'role_id' => $validated['role_id'] ?? null,
            ],
            'evaluation' => $evaluation,
            'target' => $target,
        ]);
    }
}
