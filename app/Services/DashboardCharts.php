<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use App\Models\RequestApproval;
use App\Models\RoleRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos de los gráficos del dashboard (context.md §12). Todo se calcula sobre
 * un período de `$days` días, salvo la antigüedad de pendientes (foto de hoy).
 */
class DashboardCharts
{
    public const PERIODS = [30, 90, 180, 365];

    public const DEFAULT_PERIOD = 90;

    private const AGING_BUCKETS = [
        ['label' => '0–1 días', 'max' => 1],
        ['label' => '2–3 días', 'max' => 3],
        ['label' => '4–7 días', 'max' => 7],
        ['label' => 'Más de 7 días', 'max' => null],
    ];

    public static function period(mixed $value): int
    {
        return in_array((int) $value, self::PERIODS, true) ? (int) $value : self::DEFAULT_PERIOD;
    }

    /** @return array<string, mixed> */
    public function build(int $days): array
    {
        $since = now()->subDays($days)->startOfDay();

        return [
            'days' => $days,
            'by_status' => $this->byStatus($since),
            'conflicts_trend' => $this->conflictsTrend($days),
            'aging' => $this->aging(),
            'top_apps' => $this->topApps($since),
            'top_roles' => $this->topRoles($since),
        ];
    }

    /** Solicitudes del período por estado (todos los estados, en orden fijo). */
    private function byStatus(CarbonImmutable|Carbon $since): array
    {
        $counts = RoleRequest::where('created_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return array_map(fn (RequestStatus $s) => [
            'label' => $s->label(),
            'value' => (int) ($counts[$s->value] ?? 0),
        ], RequestStatus::cases());
    }

    /**
     * Conflictos SoD detectados vs aceptados (aprobados pese al conflicto), por mes.
     * Meses completos hacia atrás, entre 3 y 12 según el período.
     */
    private function conflictsTrend(int $days): array
    {
        $months = max(3, min(12, (int) ceil($days / 30)));
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = RoleRequest::where('has_sod_conflict', true)
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'status']);

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $inMonth = $rows->filter(fn ($r) => $r->created_at->isSameMonth($m));

            $out[] = [
                'label' => $m->locale('es')->translatedFormat('M Y'),
                'detectados' => $inMonth->count(),
                'aceptados' => $inMonth->where('status', RequestStatus::Aprobado)->count(),
            ];
        }

        return $out;
    }

    /** Pasos de autorización pendientes (el paso actual de solicitudes abiertas) por antigüedad. */
    private function aging(): array
    {
        $now = now();
        $buckets = array_map(fn ($b) => $b + ['value' => 0], self::AGING_BUCKETS);

        RequestApproval::where('status', ApprovalStatus::Pendiente)
            ->whereNotNull('activated_at')
            ->whereHas('roleRequest', fn ($q) => $q->whereIn('status', [RequestStatus::Pendiente, RequestStatus::EnAprobacion]))
            ->pluck('activated_at')
            ->each(function ($activatedAt) use ($now, &$buckets) {
                $age = (int) floor($activatedAt->diffInDays($now, true));
                foreach ($buckets as $i => $b) {
                    if ($b['max'] === null || $age <= $b['max']) {
                        $buckets[$i]['value']++;

                        return;
                    }
                }
            });

        return array_map(fn ($b) => ['label' => $b['label'], 'value' => $b['value']], $buckets);
    }

    private function topApps(CarbonImmutable|Carbon $since): array
    {
        return DB::table('role_requests as rq')
            ->join('roles as r', 'r.id', '=', 'rq.requested_role_id')
            ->join('applications as a', 'a.id', '=', 'r.application_id')
            ->where('rq.created_at', '>=', $since)
            ->groupBy('a.id', 'a.name')
            ->orderByRaw('COUNT(*) DESC')->orderBy('a.name')
            ->limit(5)
            ->get(['a.name as label', DB::raw('COUNT(*) as value')])
            ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])->all();
    }

    private function topRoles(CarbonImmutable|Carbon $since): array
    {
        return DB::table('role_requests as rq')
            ->join('roles as r', 'r.id', '=', 'rq.requested_role_id')
            ->join('applications as a', 'a.id', '=', 'r.application_id')
            ->where('rq.created_at', '>=', $since)
            ->groupBy('r.id', 'r.name', 'a.name')
            ->orderByRaw('COUNT(*) DESC')->orderBy('r.name')
            ->limit(5)
            ->get(['r.name as label', 'a.name as detail', DB::raw('COUNT(*) as value')])
            ->map(fn ($r) => ['label' => $r->label, 'detail' => $r->detail, 'value' => (int) $r->value])->all();
    }
}
