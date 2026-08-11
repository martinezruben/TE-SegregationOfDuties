<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte de accesos (context.md §8.9): todos los usuarios gestionados con sus
 * aplicaciones, roles y opciones (la "foto"), enriquecido con el número de
 * solicitud y las fechas de solicitud/aprobación. Filtrable por todo y con tres
 * niveles de detalle: aplicación / rol / opción.
 */
class AccessReportController extends Controller
{
    /** @var list<string> */
    private array $levels = ['app', 'rol', 'opc'];

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('reportes.ver'), 403);

        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $page = (int) $request->integer('page', 1);
        $paginator = $this->paginate($rows, $page, 25, $request->url(), $filters);

        return Inertia::render('Reportes/Accesos', [
            'filas' => $paginator,
            'filters' => $filters,
            'nivel' => $filters['level'],
            'resumen' => [
                'usuarios' => $rows->pluck('user_id')->unique()->count(),
                'aplicaciones' => $rows->pluck('app_id')->unique()->count(),
                'accesos' => $rows->count(),
            ],
            'opciones' => [
                'aplicaciones' => Application::orderBy('name')->get(['id', 'name']),
                'roles' => \App\Models\Role::with('application:id,name')->orderBy('name')->get(['id', 'name', 'application_id']),
                'areas' => Area::orderBy('name')->get(['id', 'name']),
                'cargos' => JobPosition::orderBy('name')->get(['id', 'name']),
            ],
            'can' => ['export' => (bool) $request->user()?->can('reportes.exportar')],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless((bool) $request->user()?->can('reportes.exportar'), 403);

        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $level = $filters['level'];

        $headers = ['ID', 'IGG', 'Usuario', 'Área', 'Cargo', 'Aplicación', 'Criticidad'];
        if ($level !== 'app') {
            $headers = array_merge($headers, ['Rol']);
        }
        if ($level === 'opc') {
            $headers = array_merge($headers, ['Opción', 'Clave de acción']);
        }
        $headers = array_merge($headers, ['Origen', 'Asignado', 'N° Solicitud', 'Solicitado', 'Aprobado', 'Estado solicitud']);

        $filename = 'reporte_accesos_'.$level.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows, $headers, $level) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // BOM para acentos en Excel
            fputcsv($out, $headers);

            foreach ($rows as $r) {
                $line = [$r['external_id'], $r['igg'], $r['user_name'], $r['area'], $r['cargo'], $r['app_name'], $r['criticality']];
                if ($level !== 'app') {
                    $line[] = $r['role_name'];
                }
                if ($level === 'opc') {
                    $line[] = $r['option_name'];
                    $line[] = $r['option_key'];
                }
                $line = array_merge($line, [
                    $r['source'], $r['assigned_at'], $r['request_number'],
                    $r['requested_at'], $r['approved_at'], $r['request_status'],
                ]);
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $level = (string) $request->string('level');

        return [
            'level' => in_array($level, $this->levels, true) ? $level : 'opc',
            'search' => trim((string) $request->string('search')),
            'application_id' => $request->integer('application_id') ?: null,
            'role_id' => $request->integer('role_id') ?: null,
            'area_id' => $request->integer('area_id') ?: null,
            'job_position_id' => $request->integer('job_position_id') ?: null,
            'source' => in_array($request->string('source')->toString(), ['manual', 'import', 'request'], true)
                ? $request->string('source')->toString() : null,
            'date_from' => $request->date('date_from')?->format('Y-m-d'),
            'date_to' => $request->date('date_to')?->format('Y-m-d'),
        ];
    }

    /**
     * Construye la colección plana de accesos según los filtros y el nivel.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(array $filters): Collection
    {
        $approvedSub = DB::table('request_approvals')
            ->selectRaw('MAX(resolved_at)')
            ->whereColumn('role_request_id', 'ur.role_request_id')
            ->where('status', 'aprobado');

        $base = DB::table('user_role as ur')
            ->join('managed_users as mu', 'mu.id', '=', 'ur.managed_user_id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('applications as a', 'a.id', '=', 'r.application_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'mu.area_id')
            ->leftJoin('job_positions as jp', 'jp.id', '=', 'mu.job_position_id')
            ->leftJoin('role_requests as rq', 'rq.id', '=', 'ur.role_request_id')
            ->whereNull('mu.deleted_at')
            ->whereNull('r.deleted_at')
            ->whereNull('a.deleted_at')
            ->when($filters['search'], fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('mu.name', 'like', "%{$s}%")
                ->orWhere('mu.igg', 'like', "%{$s}%")
                ->orWhere('mu.external_id', 'like', "%{$s}%")))
            ->when($filters['application_id'], fn ($q, $v) => $q->where('a.id', $v))
            ->when($filters['role_id'], fn ($q, $v) => $q->where('r.id', $v))
            ->when($filters['area_id'], fn ($q, $v) => $q->where('mu.area_id', $v))
            ->when($filters['job_position_id'], fn ($q, $v) => $q->where('mu.job_position_id', $v))
            ->when($filters['source'], fn ($q, $v) => $q->where('ur.source', $v))
            ->when($filters['date_from'], fn ($q, $v) => $q->whereDate('ur.assigned_at', '>=', $v))
            ->when($filters['date_to'], fn ($q, $v) => $q->whereDate('ur.assigned_at', '<=', $v))
            ->orderBy('mu.name')->orderBy('a.name')->orderBy('r.name')
            ->select([
                'mu.id as user_id', 'mu.external_id', 'mu.igg', 'mu.name as user_name',
                'ar.name as area', 'jp.name as cargo',
                'a.id as app_id', 'a.name as app_name', 'a.criticality_level as criticality',
                'r.id as role_id', 'r.name as role_name',
                'ur.source', 'ur.assigned_at', 'ur.role_request_id',
                'rq.id as request_number', 'rq.created_at as requested_at', 'rq.status as request_status',
            ])
            ->selectSub($approvedSub, 'approved_at')
            ->get();

        // Nivel aplicación: una fila por (usuario, aplicación).
        if ($filters['level'] === 'app') {
            return $base->groupBy(fn ($r) => $r->user_id.'-'.$r->app_id)
                ->map(fn ($g) => $this->baseRow($g->first()))
                ->values();
        }

        // Nivel rol: una fila por asignación.
        if ($filters['level'] === 'rol') {
            return $base->map(fn ($r) => $this->rolRow($r))->values();
        }

        // Nivel opción: expande cada rol en sus opciones.
        $optionsByRole = Option::whereIn('role_id', $base->pluck('role_id')->unique())
            ->get(['id', 'role_id', 'name', 'action_key'])
            ->groupBy('role_id');

        $out = collect();
        foreach ($base as $r) {
            $opts = $optionsByRole->get($r->role_id) ?? collect();
            if ($opts->isEmpty()) {
                $out->push($this->opcRow($r, null));
            } else {
                foreach ($opts as $o) {
                    $out->push($this->opcRow($r, $o));
                }
            }
        }

        return $out->values();
    }

    /** @return array<string, mixed> */
    private function baseRow(object $r): array
    {
        return [
            'user_id' => $r->user_id, 'external_id' => $r->external_id, 'igg' => $r->igg,
            'user_name' => $r->user_name, 'area' => $r->area, 'cargo' => $r->cargo,
            'app_id' => $r->app_id, 'app_name' => $r->app_name, 'criticality' => $r->criticality,
            'role_name' => '', 'option_name' => '', 'option_key' => '',
            'source' => '', 'assigned_at' => '', 'request_number' => '',
            'requested_at' => '', 'approved_at' => '', 'request_status' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function rolRow(object $r): array
    {
        return array_merge($this->baseRow($r), [
            'role_name' => $r->role_name,
            'source' => $r->source,
            'assigned_at' => $this->fmt($r->assigned_at),
            'request_number' => $r->request_number ? '#'.$r->request_number : '',
            'requested_at' => $this->fmt($r->requested_at),
            'approved_at' => $this->fmt($r->approved_at),
            'request_status' => $r->request_status ?? '',
        ]);
    }

    /** @return array<string, mixed> */
    private function opcRow(object $r, ?object $o): array
    {
        return array_merge($this->rolRow($r), [
            'option_name' => $o?->name ?? '—',
            'option_key' => $o?->action_key ?? '',
        ]);
    }

    private function fmt(?string $value): string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '';
    }

    /**
     * Paginación en memoria del resultado ya transformado.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function paginate(Collection $rows, int $page, int $perPage, string $path, array $filters): LengthAwarePaginator
    {
        $page = max($page, 1);
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $rows->count(), $perPage, $page, [
            'path' => $path,
            'query' => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
        ]);
    }
}
