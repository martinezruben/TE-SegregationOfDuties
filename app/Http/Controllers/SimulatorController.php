<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ManagedUser;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationResolver;
use App\Services\SodEvaluator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SimulatorController extends Controller
{
    public function index(Request $request, SodEvaluator $evaluator, AuthorizationResolver $resolver): Response
    {
        abort_unless((bool) $request->user()?->can('simulador.ver'), 403);

        $validated = $request->validate([
            'managed_user_id' => ['nullable', 'exists:managed_users,id'],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $evaluation = null;
        $target = null;
        $authorization = null;

        if (! empty($validated['managed_user_id']) && ! empty($validated['role_id'])) {
            $user = ManagedUser::with(['jobPosition', 'supervisor:id,name'])->findOrFail($validated['managed_user_id']);
            $role = Role::with('application:id,name')->findOrFail($validated['role_id']);
            $evaluation = $evaluator->evaluate($user, $role);
            $target = [
                'user' => ['id' => $user->id, 'name' => $user->name, 'igg' => $user->igg],
                'role' => ['id' => $role->id, 'name' => $role->name, 'application' => $role->application?->name],
            ];
            $authorization = $this->planPreview($resolver->resolve($user, $role, $evaluation), $user);
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
            'authorization' => $authorization,
        ]);
    }

    /**
     * Traduce el plan de autorización a una vista legible para el simulador.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function planPreview(array $plan, ManagedUser $user): array
    {
        $names = User::whereIn('id', collect($plan['steps'])->pluck('authorizer_id')->filter())
            ->pluck('name', 'id');

        $steps = array_map(function (array $s) use ($names, $user) {
            $isSupervisor = $s['authorizer_type'] === 'supervisor';

            return [
                'authorizer' => $isSupervisor
                    ? ($user->supervisor?->name ?? 'Su supervisor (sin asignar)')
                    : ($names[$s['authorizer_id']] ?? 'Sin asignar'),
                'authorizer_type' => $s['authorizer_type'],
                'reasons' => $s['reasons'],
                'unresolved' => $s['authorizer_id'] === null,
            ];
        }, $plan['steps']);

        return [
            'matched' => $plan['matched'],
            'steps' => $steps,
            'supervisor_required' => $plan['supervisor_required'],
            'supervisor_missing' => $plan['supervisor_missing'],
        ];
    }
}
