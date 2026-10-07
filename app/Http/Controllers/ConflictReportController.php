<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsCsv;
use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Services\ConflictScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte de conflictos activos (context.md §12): usuarios que HOY poseen
 * combinaciones de roles en conflicto según la matriz SoD (revisión periódica).
 */
class ConflictReportController extends Controller
{
    use StreamsCsv;

    public function __construct(private ConflictScanner $scanner) {}

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('reportes.ver'), 403);

        $filters = $this->filters($request);

        $filas = $this->scanner->select($this->scanner->query($filters))
            ->paginate(25)->withQueryString()
            ->through(fn ($r) => $this->row($r));

        return Inertia::render('Reportes/Conflictos', [
            'filas' => $filas,
            'filters' => $filters,
            'resumen' => $this->scanner->summary($filters),
            'opciones' => [
                'aplicaciones' => Application::orderBy('name')->get(['id', 'name']),
                'areas' => Area::orderBy('name')->get(['id', 'name']),
                'cargos' => JobPosition::orderBy('name')->get(['id', 'name']),
            ],
            'can' => ['export' => (bool) $request->user()->can('reportes.exportar')],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless((bool) $request->user()?->can('reportes.exportar'), 403);

        $rows = $this->scanner->select($this->scanner->query($this->filters($request)))->cursor()
            ->map(function ($r) {
                $r = $this->row($r);

                return [
                    $r['external_id'], $r['igg'], $r['user_name'], $r['active'] ? 'Sí' : 'No', $r['area'], $r['cargo'],
                    $r['risk_level'], $r['app_a'], $r['role_a'], $r['assigned_a'], $r['source_a'],
                    $r['app_b'], $r['role_b'], $r['assigned_b'], $r['source_b'],
                    $r['description'], $r['exception_request_id'] ? '#'.$r['exception_request_id'] : 'Sin excepción formal',
                ];
            });

        return $this->csv('conflictos_activos_'.now()->format('Ymd_His').'.csv', [
            'ID', 'IGG', 'Usuario', 'Activo', 'Área', 'Cargo', 'Riesgo',
            'Aplicación A', 'Rol A', 'Asignado A', 'Origen A',
            'Aplicación B', 'Rol B', 'Asignado B', 'Origen B',
            'Descripción del conflicto', 'Excepción (solicitud)',
        ], $rows);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'application_id' => $request->integer('application_id') ?: null,
            'area_id' => $request->integer('area_id') ?: null,
            'job_position_id' => $request->integer('job_position_id') ?: null,
            'risk_level' => in_array($request->string('risk_level')->toString(), ['alto', 'medio', 'bajo'], true)
                ? $request->string('risk_level')->toString() : null,
            'exception' => in_array($request->string('exception')->toString(), ['con', 'sin'], true)
                ? $request->string('exception')->toString() : null,
        ];
    }

    /** @return array<string, mixed> */
    private function row(object $r): array
    {
        $fmt = fn ($v) => $v ? Carbon::parse($v)->format('Y-m-d') : '';

        return [
            'user_id' => $r->user_id, 'external_id' => $r->external_id, 'igg' => $r->igg,
            'user_name' => $r->user_name, 'active' => (bool) $r->is_active,
            'area' => $r->area, 'cargo' => $r->cargo,
            'risk_level' => $r->risk_level, 'description' => $r->description,
            'role_a' => $r->role_a, 'app_a' => $r->app_a, 'source_a' => $r->source_a, 'assigned_a' => $fmt($r->assigned_a),
            'role_b' => $r->role_b, 'app_b' => $r->app_b, 'source_b' => $r->source_b, 'assigned_b' => $fmt($r->assigned_b),
            'exception_request_id' => $r->exception_request_id ? (int) $r->exception_request_id : null,
        ];
    }
}
