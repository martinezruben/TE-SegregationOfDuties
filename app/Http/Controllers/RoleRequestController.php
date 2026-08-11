<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\RoleRequest;
use App\Services\ApprovalWorkflowBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleRequestController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('solicitudes.ver'), 403);

        $solicitudes = RoleRequest::query()
            ->with(['managedUser:id,name,igg', 'requestedRole:id,name,application_id', 'requestedRole.application:id,name', 'requester:id,name'])
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (RoleRequest $r) => $this->serialize($r));

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => $solicitudes,
        ]);
    }

    public function show(Request $request, RoleRequest $solicitud): Response
    {
        abort_unless((bool) $request->user()?->can('solicitudes.ver'), 403);

        $solicitud->load([
            'managedUser:id,name,igg,area_id,job_position_id',
            'managedUser.area:id,name',
            'managedUser.jobPosition:id,name',
            'requestedRole:id,name,application_id',
            'requestedRole.application:id,name,criticality_level',
            'requester:id,name',
            'approvals.approver:id,name',
            'findings.conflictingRole:id,name',
        ]);

        $current = $solicitud->currentStep();
        $user = $request->user();
        $canApprove = $user->can('solicitudes.autorizar')
            && (! $solicitud->is_major || $user->can('solicitudes.autorizar_mayor'))
            && $current !== null
            && $current->approver_id === $user->id;

        $payload = $solicitud->payload ?? [];

        return Inertia::render('Solicitudes/Show', [
            'solicitud' => [
                'id' => $solicitud->id,
                'type' => $solicitud->type->value,
                'status' => $solicitud->status->value,
                'status_label' => $solicitud->status->label(),
                'status_variant' => $solicitud->status->badgeVariant(),
                'is_major' => $solicitud->is_major,
                'auto_resolved' => $solicitud->auto_resolved,
                'has_sod_conflict' => $solicitud->has_sod_conflict,
                'justification' => $solicitud->justification,
                'created_at' => $solicitud->created_at?->format('Y-m-d H:i'),
                'requester' => $solicitud->requester?->name,
                'user' => [
                    'name' => $solicitud->managedUser?->name,
                    'igg' => $solicitud->managedUser?->igg,
                    'area' => $solicitud->managedUser?->area?->name,
                    'cargo' => $solicitud->managedUser?->jobPosition?->name,
                ],
                'role' => $solicitud->requestedRole?->name,
                'application' => $solicitud->requestedRole?->application?->name,
                'criticality' => $solicitud->requestedRole?->application?->criticality_level?->value,
                'cargo' => $payload['cargo'] ?? null,
                'reasons' => $payload['reasons'] ?? [],
                'findings' => $solicitud->findings->map(fn ($f) => [
                    'role' => $f->conflictingRole?->name,
                    'risk_level' => $f->risk_level,
                ]),
                'approvals' => $solicitud->approvals->map(fn ($a) => [
                    'step_order' => $a->step_order,
                    'approver_role' => $a->approver_role,
                    'approver' => $a->approver?->name,
                    'status' => $a->status->value,
                    'is_auto' => $a->is_auto,
                    'comments' => $a->comments,
                    'resolved_at' => $a->resolved_at?->format('Y-m-d H:i'),
                    'is_current' => $current !== null && $a->id === $current->id,
                ]),
            ],
            'canApprove' => $canApprove,
        ]);
    }

    public function store(Request $request, ApprovalWorkflowBuilder $builder): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('solicitudes.crear'), 403);

        $data = $request->validate([
            'managed_user_id' => ['required', 'exists:managed_users,id'],
            'requested_role_id' => ['required', 'exists:roles,id'],
            'justification' => ['nullable', 'string', 'max:1000'],
        ]);

        $roleRequest = RoleRequest::create([
            'managed_user_id' => $data['managed_user_id'],
            'requested_role_id' => $data['requested_role_id'],
            'type' => RequestType::Asignacion,
            'status' => RequestStatus::Pendiente,
            'requester_id' => $request->user()->id,
            'justification' => $data['justification'] ?? null,
        ]);

        $builder->build($roleRequest);

        $msg = $roleRequest->fresh()->status === RequestStatus::AutoAprobado
            ? "Solicitud #{$roleRequest->id} auto-aprobada (cargo permitido y sin conflicto)."
            : "Solicitud #{$roleRequest->id} creada y enviada a autorización.";

        // Volvemos a la pantalla de origen (p. ej. el Simulador) para mostrar el aviso.
        return back()->with('success', $msg);
    }

    public function approve(Request $request, RoleRequest $solicitud, ApprovalWorkflowBuilder $builder): RedirectResponse
    {
        $this->authorizeStep($request, $solicitud);

        $step = $solicitud->currentStep();
        $step->update([
            'status' => ApprovalStatus::Aprobado,
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'comments' => $request->string('comments')->toString() ?: null,
        ]);

        $next = $solicitud->approvals()->where('status', ApprovalStatus::Pendiente)->exists();
        if ($next) {
            $solicitud->update(['status' => RequestStatus::EnAprobacion]);
        } else {
            $solicitud->update(['status' => RequestStatus::Aprobado]);
            $builder->applyApproved($solicitud);
        }

        return back()->with('success', 'Paso aprobado.');
    }

    public function reject(Request $request, RoleRequest $solicitud): RedirectResponse
    {
        $this->authorizeStep($request, $solicitud);

        $solicitud->currentStep()->update([
            'status' => ApprovalStatus::Rechazado,
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'comments' => $request->string('comments')->toString() ?: null,
        ]);

        $solicitud->update(['status' => RequestStatus::Rechazado]);

        return back()->with('success', 'Solicitud rechazada.');
    }

    private function authorizeStep(Request $request, RoleRequest $solicitud): void
    {
        $user = $request->user();
        abort_unless((bool) $user?->can('solicitudes.autorizar'), 403);
        if ($solicitud->is_major) {
            abort_unless((bool) $user->can('solicitudes.autorizar_mayor'), 403);
        }

        $solicitud->load('approvals');
        $step = $solicitud->currentStep();
        abort_unless($step !== null && $step->approver_id === $user->id, 403, 'No eres el aprobador de este paso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(RoleRequest $r): array
    {
        return [
            'id' => $r->id,
            'user' => $r->managedUser?->name,
            'igg' => $r->managedUser?->igg,
            'role' => $r->requestedRole?->name,
            'application' => $r->requestedRole?->application?->name,
            'type' => $r->type->value,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'status_variant' => $r->status->badgeVariant(),
            'has_sod_conflict' => $r->has_sod_conflict,
            'requester' => $r->requester?->name,
            'created_at' => $r->created_at?->format('Y-m-d H:i'),
        ];
    }
}
