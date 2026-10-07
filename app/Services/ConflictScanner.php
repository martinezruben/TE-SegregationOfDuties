<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Escaneo de conflictos SoD YA existentes en la "foto" (context.md §12):
 * cruza, a nivel SQL, la foto vigente de cada usuario gestionado contra la matriz activa.
 * No depende de solicitudes: detecta también accesos importados o manuales.
 */
class ConflictScanner
{
    /**
     * Una fila por (usuario, par en conflicto) con ambos roles vigentes.
     *
     * @param  array{search?:?string, application_id?:?int, area_id?:?int, job_position_id?:?int, risk_level?:?string, exception?:?string}  $filters
     */
    public function query(array $filters = []): Builder
    {
        $now = now();

        $q = DB::table('sod_matrix as m')
            ->join('user_role as a', 'a.role_id', '=', 'm.role_a_id')
            ->join('user_role as b', function ($j) {
                $j->on('b.role_id', '=', 'm.role_b_id')->on('b.managed_user_id', '=', 'a.managed_user_id');
            })
            ->join('managed_users as mu', 'mu.id', '=', 'a.managed_user_id')
            ->join('roles as ra', 'ra.id', '=', 'm.role_a_id')
            ->join('roles as rb', 'rb.id', '=', 'm.role_b_id')
            ->join('applications as aa', 'aa.id', '=', 'ra.application_id')
            ->join('applications as ab', 'ab.id', '=', 'rb.application_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'mu.area_id')
            ->leftJoin('job_positions as jp', 'jp.id', '=', 'mu.job_position_id')
            // Excepción formal: la solicitud aprobada que otorgó uno de los roles registró este mismo conflicto.
            ->leftJoin('request_sod_findings as fa', function ($j) {
                $j->on('fa.role_request_id', '=', 'a.role_request_id')->on('fa.conflicting_role_id', '=', 'm.role_b_id');
            })
            ->leftJoin('role_requests as qa', function ($j) {
                $j->on('qa.id', '=', 'fa.role_request_id')->where('qa.status', 'aprobado');
            })
            ->leftJoin('request_sod_findings as fb', function ($j) {
                $j->on('fb.role_request_id', '=', 'b.role_request_id')->on('fb.conflicting_role_id', '=', 'm.role_a_id');
            })
            ->leftJoin('role_requests as qb', function ($j) {
                $j->on('qb.id', '=', 'fb.role_request_id')->where('qb.status', 'aprobado');
            })
            ->where('m.is_active', true)
            ->whereNull('mu.deleted_at')
            ->whereNull('ra.deleted_at')->whereNull('rb.deleted_at')
            ->whereNull('aa.deleted_at')->whereNull('ab.deleted_at')
            ->where(fn ($w) => $w->whereNull('a.expires_at')->orWhere('a.expires_at', '>', $now))
            ->where(fn ($w) => $w->whereNull('b.expires_at')->orWhere('b.expires_at', '>', $now));

        $q->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
            ->where('mu.name', 'like', "%{$s}%")
            ->orWhere('mu.igg', 'like', "%{$s}%")
            ->orWhere('mu.external_id', 'like', "%{$s}%")))
            ->when($filters['application_id'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('aa.id', $v)->orWhere('ab.id', $v)))
            ->when($filters['area_id'] ?? null, fn ($q, $v) => $q->where('mu.area_id', $v))
            ->when($filters['job_position_id'] ?? null, fn ($q, $v) => $q->where('mu.job_position_id', $v))
            ->when($filters['risk_level'] ?? null, fn ($q, $v) => $q->where('m.risk_level', $v))
            ->when(($filters['exception'] ?? null) === 'con', fn ($q) => $q->where(fn ($w) => $w->whereNotNull('qa.id')->orWhereNotNull('qb.id')))
            ->when(($filters['exception'] ?? null) === 'sin', fn ($q) => $q->whereNull('qa.id')->whereNull('qb.id'));

        return $q;
    }

    /** Columnas del listado. */
    public function select(Builder $q): Builder
    {
        return $q->select([
            'mu.id as user_id', 'mu.external_id', 'mu.igg', 'mu.name as user_name', 'mu.is_active',
            'ar.name as area', 'jp.name as cargo',
            'm.id as matrix_id', 'm.risk_level', 'm.description',
            'ra.name as role_a', 'aa.name as app_a', 'a.source as source_a', 'a.assigned_at as assigned_a',
            'rb.name as role_b', 'ab.name as app_b', 'b.source as source_b', 'b.assigned_at as assigned_b',
        ])->selectRaw('COALESCE(qa.id, qb.id) as exception_request_id')
            ->orderByRaw("CASE m.risk_level WHEN 'alto' THEN 0 WHEN 'medio' THEN 1 ELSE 2 END")
            ->orderBy('mu.name')->orderBy('m.id');
    }

    /**
     * Totales por riesgo y usuarios distintos afectados.
     *
     * @return array{total:int, usuarios:int, alto:int, medio:int, bajo:int, con_excepcion:int, sin_excepcion:int}
     */
    public function summary(array $filters = []): array
    {
        $rows = $this->query($filters)
            ->selectRaw('m.risk_level as risk, COUNT(*) as n, COUNT(DISTINCT mu.id) as users')
            ->selectRaw('SUM(CASE WHEN qa.id IS NOT NULL OR qb.id IS NOT NULL THEN 1 ELSE 0 END) as exc')
            ->groupBy('m.risk_level')->get();

        $users = (clone $this->query($filters))->distinct()->count('mu.id');

        return [
            'total' => (int) $rows->sum('n'),
            'usuarios' => $users,
            'alto' => (int) $rows->firstWhere('risk', 'alto')?->n,
            'medio' => (int) $rows->firstWhere('risk', 'medio')?->n,
            'bajo' => (int) $rows->firstWhere('risk', 'bajo')?->n,
            'con_excepcion' => (int) $rows->sum('exc'),
            'sin_excepcion' => (int) $rows->sum('n') - (int) $rows->sum('exc'),
        ];
    }
}
