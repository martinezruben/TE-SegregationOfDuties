<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use App\Models\RequestApproval;
use App\Models\RequestSodFinding;
use App\Models\RoleRequest;
use App\Models\User;

/**
 * Construye el flujo de aprobación de una solicitud según la matriz de
 * autorizaciones configurable (context.md §8.5), consolidando autorizadores,
 * o la auto-aprueba. Si no hay reglas configuradas que apliquen, usa el flujo
 * por defecto (owner de aplicación + seguridad).
 */
class ApprovalWorkflowBuilder
{
    public function __construct(
        private SodEvaluator $evaluator,
        private AuthorizationResolver $resolver,
        private ApprovalNotifier $notifier,
    ) {}

    public function build(RoleRequest $request): void
    {
        $request->loadMissing(['managedUser.jobPosition', 'managedUser.supervisor', 'requestedRole.application']);
        $user = $request->managedUser;
        $role = $request->requestedRole;

        $eval = $this->evaluator->evaluate($user, $role);
        $plan = $this->resolver->resolve($user, $role, $eval);

        // Registrar hallazgos SoD.
        foreach ($eval['conflicts'] as $c) {
            RequestSodFinding::create([
                'role_request_id' => $request->id,
                'conflicting_role_id' => $c['role_id'],
                'risk_level' => $c['risk_level'],
            ]);
        }

        $request->has_sod_conflict = $eval['has_conflict'];

        // Snapshot de lo validado, para mostrarlo en la pantalla de aprobación.
        $request->payload = [
            'cargo' => $eval['cargo'],
            'has_conflict' => $eval['has_conflict'],
            'reasons' => $this->reasons($request, $eval),
            'supervisor_missing' => $plan['supervisor_missing'],
        ];

        // Auto-aprobación: cargo permitido, sin conflicto, no es autorización
        // mayor y ninguna regla de la matriz de autorizaciones aplica.
        if ($eval['cargo']['allowed'] && ! $eval['has_conflict'] && ! $request->is_major && ! $plan['matched']) {
            $request->status = RequestStatus::AutoAprobado;
            $request->auto_resolved = true;
            $request->save();

            RequestApproval::create([
                'role_request_id' => $request->id,
                'approver_role' => 'sistema',
                'step_order' => 1,
                'status' => ApprovalStatus::Aprobado,
                'is_auto' => true,
                'resolved_at' => now(),
            ]);

            $this->applyApproved($request);

            return;
        }

        // Construir los pasos: matriz configurable o flujo por defecto.
        $steps = $plan['matched']
            ? $this->stepsFromPlan($plan, $request)
            : $this->legacySteps($request, $eval['has_conflict']);

        $order = 1;
        foreach ($steps as $step) {
            RequestApproval::create([
                'role_request_id' => $request->id,
                'approver_id' => $this->antiSelf($step['approver_id'], $request),
                'approver_role' => $step['approver_role'],
                'reasons' => $step['reasons'],
                'step_order' => $order++,
                'status' => ApprovalStatus::Pendiente,
            ]);
        }

        $request->status = RequestStatus::EnAprobacion;
        $request->save();

        $this->notifier->stepActivated($request);
        $this->notifier->highRiskAlert($request);
    }

    /**
     * Aplica la aprobación a la "foto" del usuario gestionado.
     */
    public function applyApproved(RoleRequest $request): void
    {
        $request->managedUser->roles()->syncWithoutDetaching([
            $request->requested_role_id => [
                'assigned_at' => now(),
                'assigned_by' => $request->requester_id,
                'source' => 'request',
                'role_request_id' => $request->id,
            ],
        ]);
    }

    /**
     * Pasos derivados de la matriz de autorizaciones, ya consolidados.
     *
     * @param  array<string, mixed>  $plan
     * @return list<array{approver_id:?int, approver_role:string, reasons:list<string>}>
     */
    private function stepsFromPlan(array $plan, RoleRequest $request): array
    {
        return array_map(function (array $step): array {
            // Autorizador sin resolver (jefe no asignado o persona fija vacía):
            // se envía a Seguridad como respaldo para no bloquear el flujo.
            $approverId = $step['authorizer_id'] ?? $this->securityUserId() ?? $this->adminId();

            $role = $step['authorizer_type'] === 'supervisor' ? 'jefe' : 'autorizador';
            if ($step['authorizer_type'] === 'supervisor' && $step['authorizer_id'] === null) {
                $role = 'seguridad'; // respaldo por jefe no asignado
            }

            return [
                'approver_id' => $approverId,
                'approver_role' => $role,
                'reasons' => $step['reasons'],
            ];
        }, $plan['steps']);
    }

    /**
     * Flujo por defecto cuando no hay reglas configuradas que apliquen.
     *
     * @return list<array{approver_id:?int, approver_role:string, reasons:list<string>}>
     */
    private function legacySteps(RoleRequest $request, bool $hasConflict): array
    {
        $app = $request->requestedRole->application;
        $crit = $app?->criticality_level?->value ?? 'media';

        $roles = ($request->is_major || $crit === 'alta') ? ['owner_app', 'seguridad'] : ['owner_app'];
        if ($hasConflict && ! in_array('seguridad', $roles, true)) {
            $roles[] = 'seguridad';
        }

        return array_map(fn (string $r) => [
            'approver_id' => $r === 'owner_app'
                ? ($app?->owner_id ?? $this->securityUserId() ?? $this->adminId())
                : ($this->securityUserId() ?? $this->adminId()),
            'approver_role' => $r,
            'reasons' => [],
        ], $roles);
    }

    /**
     * Razones (legibles) por las que la solicitud requiere aprobación.
     *
     * @param  array<string, mixed>  $eval
     * @return list<string>
     */
    private function reasons(RoleRequest $request, array $eval): array
    {
        $reasons = [];

        if ($request->is_major) {
            $reasons[] = 'Es una modificación de rol (autorización mayor).';
        }
        if (! $eval['cargo']['allowed']) {
            $reasons[] = $eval['cargo']['status'] === 'no_permitido'
                ? 'El cargo del usuario tiene el rol como NO permitido.'
                : 'El cargo del usuario no tiene el rol como permitido (sin regla).';
        }
        if ($eval['has_conflict']) {
            $reasons[] = 'Genera conflicto(s) de Segregación de Funciones con la foto actual.';
        }
        if (($request->requestedRole->application?->criticality_level?->value ?? 'media') === 'alta') {
            $reasons[] = 'La aplicación es de criticidad alta.';
        }

        return $reasons;
    }

    private function antiSelf(?int $candidate, RoleRequest $request): ?int
    {
        if ($candidate !== null && $candidate === $request->requester_id) {
            return $this->securityUserId($request->requester_id) ?? $this->adminId($request->requester_id) ?? $candidate;
        }

        return $candidate;
    }

    private function securityUserId(?int $exceptId = null): ?int
    {
        return User::role('Gestor de Seguridad')
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->value('id');
    }

    private function adminId(?int $exceptId = null): ?int
    {
        return User::role('Administrador')
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->value('id');
    }
}
