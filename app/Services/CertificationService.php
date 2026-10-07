<?php

namespace App\Services;

use App\Models\CertificationCampaign;
use App\Models\CertificationItem;
use App\Models\ManagedUser;
use App\Models\User;
use App\Notifications\CertificationAssigned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recertificación periódica: genera la campaña desde la foto vigente, asigna
 * revisor por acceso, registra decisiones (mantener / revocar) y cierra.
 */
class CertificationService
{
    private const RISK_RANK = ['bajo' => 1, 'medio' => 2, 'alto' => 3];

    public function __construct(private ConflictScanner $scanner) {}

    /**
     * @param  array{name:string, due_date:string, application_id?:?int, area_id?:?int, only_conflicts?:bool}  $data
     */
    public function createCampaign(array $data, User $by): CertificationCampaign
    {
        $filters = [
            'application_id' => $data['application_id'] ?? null,
            'area_id' => $data['area_id'] ?? null,
            'only_conflicts' => (bool) ($data['only_conflicts'] ?? false),
        ];

        $conflictRisk = $this->conflictRiskByAccess();
        $fallbackIds = $this->fallbackReviewers($by);

        $rows = DB::table('user_role as ur')
            ->join('managed_users as mu', 'mu.id', '=', 'ur.managed_user_id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('applications as a', 'a.id', '=', 'r.application_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'mu.area_id')
            ->leftJoin('job_positions as jp', 'jp.id', '=', 'mu.job_position_id')
            ->whereNull('mu.deleted_at')->whereNull('r.deleted_at')->whereNull('a.deleted_at')
            ->where(fn ($w) => $w->whereNull('ur.expires_at')->orWhere('ur.expires_at', '>', now()))
            ->when($filters['application_id'], fn ($q, $v) => $q->where('a.id', $v))
            ->when($filters['area_id'], fn ($q, $v) => $q->where('mu.area_id', $v))
            ->orderBy('mu.name')->orderBy('a.name')->orderBy('r.name')
            ->get([
                'ur.managed_user_id', 'ur.role_id', 'ur.source', 'ur.assigned_at', 'ur.expires_at',
                'mu.name as user_name', 'mu.igg as user_igg', 'mu.supervisor_id',
                'ar.name as area', 'jp.name as cargo',
                'a.name as app_name', 'a.owner_id', 'r.name as role_name',
            ]);

        if ($filters['only_conflicts']) {
            $rows = $rows->filter(fn ($r) => isset($conflictRisk[$r->managed_user_id.'|'.$r->role_id]));
        }

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['name' => 'Con esos filtros no hay accesos vigentes para certificar.']);
        }

        return DB::transaction(function () use ($data, $by, $filters, $rows, $conflictRisk, $fallbackIds) {
            $campaign = CertificationCampaign::create([
                'name' => $data['name'],
                'due_date' => $data['due_date'],
                'filters' => $filters,
                'created_by' => $by->id,
            ]);

            $now = now();
            $rows->chunk(500)->each(fn (Collection $chunk) => CertificationItem::insert(
                $chunk->map(fn ($r) => [
                    'campaign_id' => $campaign->id,
                    'managed_user_id' => $r->managed_user_id,
                    'role_id' => $r->role_id,
                    'reviewer_id' => $this->reviewerFor($r, $fallbackIds),
                    'user_name' => $r->user_name, 'user_igg' => $r->user_igg, 'area' => $r->area, 'cargo' => $r->cargo,
                    'app_name' => $r->app_name, 'role_name' => $r->role_name, 'source' => $r->source,
                    'assigned_at' => $r->assigned_at, 'expires_at' => $r->expires_at,
                    'conflict_risk' => $conflictRisk[$r->managed_user_id.'|'.$r->role_id] ?? null,
                    'decision' => 'pendiente',
                    'created_at' => $now, 'updated_at' => $now,
                ])->all(),
            ));

            return $campaign;
        });
    }

    /** Avisa a cada revisor cuántos accesos tiene pendientes en la campaña. */
    public function notifyReviewers(CertificationCampaign $campaign): void
    {
        $campaign->items()->where('decision', 'pendiente')
            ->selectRaw('reviewer_id, COUNT(*) as n')->whereNotNull('reviewer_id')->groupBy('reviewer_id')
            ->get()
            ->each(function ($g) use ($campaign) {
                User::find($g->reviewer_id)?->notify(new CertificationAssigned($campaign, (int) $g->n));
            });
    }

    /**
     * Registra la decisión sobre ítems propios y pendientes de una campaña abierta.
     * "revocar" retira el acceso de la foto de inmediato (la baja técnica en el sistema destino sigue siendo manual).
     *
     * @param  list<int>  $itemIds
     * @return array{decididos:int, omitidos:int}
     */
    public function decide(CertificationCampaign $campaign, array $itemIds, string $decision, ?string $comments, User $by): array
    {
        if (! $campaign->isOpen()) {
            throw ValidationException::withMessages(['decision' => 'La campaña está cerrada.']);
        }
        if ($decision === 'revocar' && trim((string) $comments) === '') {
            throw ValidationException::withMessages(['comments' => 'Indica el motivo de la revocación.']);
        }

        $decided = DB::transaction(function () use ($campaign, $itemIds, $decision, $comments, $by) {
            $items = $campaign->items()
                ->whereIn('id', $itemIds)
                ->where('reviewer_id', $by->id)
                ->where('decision', 'pendiente')
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $item->update([
                    'decision' => $decision,
                    'decided_by' => $by->id,
                    'decided_at' => now(),
                    'comments' => $comments ? trim($comments) : null,
                ]);

                if ($decision === 'revocar') {
                    ManagedUser::withTrashed()->find($item->managed_user_id)?->roles()->detach($item->role_id);
                }
            }

            return $items->count();
        });

        $this->closeIfComplete($campaign);

        return ['decididos' => $decided, 'omitidos' => count(array_unique($itemIds)) - $decided];
    }

    public function close(CertificationCampaign $campaign, ?User $by): void
    {
        if ($campaign->isOpen()) {
            $campaign->update(['status' => 'cerrada', 'closed_at' => now(), 'closed_by' => $by?->id]);
        }
    }

    /** Cierra la campaña automáticamente cuando ya no quedan accesos pendientes. */
    private function closeIfComplete(CertificationCampaign $campaign): void
    {
        if (! $campaign->items()->where('decision', 'pendiente')->exists()) {
            $this->close($campaign->fresh(), null);
        }
    }

    /**
     * Recordatorios a revisores con pendientes en campañas abiertas que vencen en
     * `reminder_days_before` días o ya vencieron (máximo uno por revisor y día).
     *
     * @return int cantidad de revisores recordados
     */
    public function remindDue(): int
    {
        $limit = now()->addDays((int) config('sod.certification.reminder_days_before'))->toDateString();
        $count = 0;

        CertificationCampaign::where('status', 'abierta')->whereDate('due_date', '<=', $limit)->get()
            ->each(function (CertificationCampaign $c) use (&$count) {
                $c->items()->where('decision', 'pendiente')->whereNotNull('reviewer_id')
                    ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<=', now()->subDay()))
                    ->selectRaw('reviewer_id, COUNT(*) as n')->groupBy('reviewer_id')->get()
                    ->each(function ($g) use ($c, &$count) {
                        User::find($g->reviewer_id)?->notify(new CertificationAssigned($c, (int) $g->n, reminder: true));
                        $c->items()->where('reviewer_id', $g->reviewer_id)->where('decision', 'pendiente')->update(['reminded_at' => now()]);
                        $count++;
                    });
            });

        return $count;
    }

    /**
     * Revisor de un acceso: owner de la aplicación → supervisor del usuario →
     * Gestor de Seguridad → quien creó la campaña.
     *
     * @param  list<int>  $fallbackIds  [seguridad, creador]
     */
    private function reviewerFor(object $row, array $fallbackIds): ?int
    {
        return $row->owner_id ?? $row->supervisor_id ?? $fallbackIds['security'] ?? $fallbackIds['creator'];
    }

    /** @return array{security:?int, creator:int} */
    private function fallbackReviewers(User $by): array
    {
        return [
            'security' => User::role('Gestor de Seguridad')->value('id'),
            'creator' => $by->id,
        ];
    }

    /**
     * Mayor riesgo SoD por acceso ("usuario|rol") según la matriz activa.
     *
     * @return array<string, string>
     */
    private function conflictRiskByAccess(): array
    {
        $map = [];
        $rows = $this->scanner->query()->get([
            'a.managed_user_id as user_id', 'm.role_a_id', 'm.role_b_id', 'm.risk_level',
        ]);

        foreach ($rows as $r) {
            foreach ([$r->role_a_id, $r->role_b_id] as $roleId) {
                $key = $r->user_id.'|'.$roleId;
                if (! isset($map[$key]) || self::RISK_RANK[$r->risk_level] > self::RISK_RANK[$map[$key]]) {
                    $map[$key] = $r->risk_level;
                }
            }
        }

        return $map;
    }
}
