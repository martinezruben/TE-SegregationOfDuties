<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use App\Models\RequestApproval;
use App\Models\RoleRequest;
use App\Models\User;
use App\Notifications\ApprovalPending;
use App\Notifications\HighRiskSodAlert;
use App\Notifications\RequestResolved;

/**
 * Orquesta las notificaciones del workflow (context.md §8.4.4 y §11).
 */
class ApprovalNotifier
{
    /** Marca el paso pendiente actual (el de menor step_order), inicia su SLA y avisa al aprobador. */
    public function stepActivated(RoleRequest $request): void
    {
        $step = $request->approvals()
            ->where('status', ApprovalStatus::Pendiente)
            ->orderBy('step_order')
            ->first();

        if ($step === null) {
            return;
        }

        $step->update(['activated_at' => now(), 'reminded_at' => null, 'reminder_count' => 0]);

        $step->approver?->notify(new ApprovalPending($step));
    }

    /** Avisa al solicitante del resultado final. */
    public function resolved(RoleRequest $request, bool $approved, ?string $comments = null): void
    {
        $request->requester?->notify(new RequestResolved($request, $approved, $comments));
    }

    /** Avisa a los Gestores de Seguridad si hay un conflicto SoD de riesgo alto. */
    public function highRiskAlert(RoleRequest $request): void
    {
        if (! $request->findings()->where('risk_level', 'alto')->exists()) {
            return;
        }

        // No duplicar al aprobador del paso actual (ya recibe "autorización pendiente") ni al solicitante.
        $skip = array_filter([
            $request->requester_id,
            $request->approvals()->where('status', ApprovalStatus::Pendiente)->orderBy('step_order')->value('approver_id'),
        ]);

        User::role('Gestor de Seguridad')
            ->whereNotIn('id', $skip)
            ->get()
            ->each(fn (User $u) => $u->notify(new HighRiskSodAlert($request)));
    }

    /**
     * Recuerda a los aprobadores con pasos vencidos según el SLA.
     *
     * @return int cantidad de recordatorios enviados
     */
    public function remindOverdue(): int
    {
        $sla = config('sod.sla');

        $steps = RequestApproval::query()
            ->with(['approver', 'roleRequest'])
            ->where('status', ApprovalStatus::Pendiente)
            ->whereNotNull('approver_id')
            ->whereNotNull('activated_at')
            ->where('activated_at', '<=', now()->subHours($sla['hours']))
            ->where('reminder_count', '<', $sla['max_reminders'])
            ->where(fn ($q) => $q
                ->whereNull('reminded_at')
                ->orWhere('reminded_at', '<=', now()->subHours($sla['reminder_every_hours'])))
            ->whereHas('roleRequest', fn ($q) => $q->whereIn('status', [RequestStatus::Pendiente, RequestStatus::EnAprobacion]))
            ->get();

        foreach ($steps as $step) {
            $step->approver?->notify(new ApprovalPending($step, reminder: true));
            $step->update(['reminded_at' => now(), 'reminder_count' => $step->reminder_count + 1]);
        }

        return $steps->count();
    }
}
