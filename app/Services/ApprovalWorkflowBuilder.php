<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use App\Models\RequestApproval;
use App\Models\RequestSodFinding;
use App\Models\RoleRequest;
use App\Models\User;

/**
 * Construye el flujo de aprobación de una solicitud según la criticidad y
 * los hallazgos SoD, o la auto-aprueba. Ver context.md §8.4.
 */
class ApprovalWorkflowBuilder
{
    public function __construct(private SodEvaluator $evaluator)
    {
    }

    public function build(RoleRequest $request): void
    {
        $request->loadMissing(['managedUser.jobPosition', 'requestedRole.application']);
        $user = $request->managedUser;
        $role = $request->requestedRole;

        $eval = $this->evaluator->evaluate($user, $role);

        // Registrar hallazgos SoD.
        foreach ($eval['conflicts'] as $c) {
            RequestSodFinding::create([
                'role_request_id' => $request->id,
                'conflicting_role_id' => $c['role_id'],
                'risk_level' => $c['risk_level'],
            ]);
        }

        $request->has_sod_conflict = $eval['has_conflict'];

        // Auto-aprobación: cargo permitido, sin conflicto y no es autorización mayor.
        if ($eval['cargo']['allowed'] && ! $eval['has_conflict'] && ! $request->is_major) {
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

        // Construir la cadena de pasos.
        $steps = $this->steps($request, $eval['has_conflict']);

        $order = 1;
        foreach ($steps as $step) {
            RequestApproval::create([
                'role_request_id' => $request->id,
                'approver_id' => $this->resolveApprover($step, $request),
                'approver_role' => $step,
                'step_order' => $order++,
                'status' => ApprovalStatus::Pendiente,
            ]);
        }

        $request->status = RequestStatus::EnAprobacion;
        $request->save();
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
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function steps(RoleRequest $request, bool $hasConflict): array
    {
        if ($request->is_major) {
            return ['owner_app', 'seguridad'];
        }

        $crit = $request->requestedRole->application?->criticality_level?->value ?? 'media';

        $steps = match ($crit) {
            'alta' => ['owner_app', 'seguridad'],
            default => ['owner_app'], // baja | media
        };

        // Un conflicto SoD fuerza el paso de Seguridad.
        if ($hasConflict && ! in_array('seguridad', $steps, true)) {
            $steps[] = 'seguridad';
        }

        return $steps;
    }

    private function resolveApprover(string $step, RoleRequest $request): ?int
    {
        $app = $request->requestedRole->application;

        $candidate = match ($step) {
            'owner_app' => $app?->owner_id ?? $this->securityUserId() ?? $this->adminId(),
            'seguridad' => $this->securityUserId() ?? $this->adminId(),
            default => $this->adminId(),
        };

        // Anti-self-approval: el aprobador no puede ser el solicitante.
        if ($candidate !== null && $candidate === $request->requester_id) {
            $candidate = $this->securityUserId($request->requester_id) ?? $this->adminId($request->requester_id) ?? $candidate;
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
