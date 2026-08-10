<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\RequestApproval;
use App\Models\RoleRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Solicitudes con un paso pendiente asignado a mí (y que sea el paso actual).
        $requestIds = RequestApproval::where('approver_id', $user->id)
            ->where('status', 'pendiente')
            ->pluck('role_request_id')
            ->unique();

        $open = RoleRequest::whereIn('id', $requestIds)
            ->whereIn('status', [RequestStatus::Pendiente->value, RequestStatus::EnAprobacion->value])
            ->with(['managedUser:id,name,igg', 'requestedRole:id,name,application_id', 'requestedRole.application:id,name', 'approvals'])
            ->get();

        $pendientes = $open
            ->filter(fn (RoleRequest $r) => $r->currentStep()?->approver_id === $user->id)
            ->map(fn (RoleRequest $r) => [
                'id' => $r->id,
                'user' => $r->managedUser?->name,
                'igg' => $r->managedUser?->igg,
                'role' => $r->requestedRole?->name,
                'application' => $r->requestedRole?->application?->name,
                'has_sod_conflict' => $r->has_sod_conflict,
                'is_major' => $r->is_major,
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'pendientes' => $pendientes,
            'kpis' => [
                'autorizaciones_pendientes' => $pendientes->count(),
                'conflictos_abiertos' => RoleRequest::whereIn('status', [RequestStatus::Pendiente->value, RequestStatus::EnAprobacion->value])
                    ->where('has_sod_conflict', true)->count(),
                'aprobadas_hoy' => RoleRequest::whereIn('status', [RequestStatus::Aprobado->value, RequestStatus::AutoAprobado->value])
                    ->whereDate('updated_at', today())->count(),
                'mis_solicitudes' => RoleRequest::where('requester_id', $user->id)
                    ->whereIn('status', [RequestStatus::Pendiente->value, RequestStatus::EnAprobacion->value])->count(),
            ],
        ]);
    }
}
