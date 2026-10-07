<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\StreamsCsv;
use App\Models\Application;
use App\Models\RoleRequest;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte de excepciones (context.md §12): accesos otorgados pese a un conflicto
 * SoD, con su justificación y los aprobadores que los autorizaron.
 */
class ExceptionReportController extends Controller
{
    use StreamsCsv;

    private const RISK_ORDER = ['alto' => 3, 'medio' => 2, 'bajo' => 1];

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('reportes.ver'), 403);

        $filters = $this->filters($request);

        $filas = $this->query($filters)->paginate(15)->withQueryString()
            ->through(fn (RoleRequest $r) => $this->row($r));

        return Inertia::render('Reportes/Excepciones', [
            'filas' => $filas,
            'filters' => $filters,
            'opciones' => ['aplicaciones' => Application::orderBy('name')->get(['id', 'name'])],
            'can' => ['export' => (bool) $request->user()->can('reportes.exportar')],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless((bool) $request->user()?->can('reportes.exportar'), 403);

        $rows = $this->query($this->filters($request))->cursor()->map(function (RoleRequest $r) {
            $x = $this->row($r);

            return [
                '#'.$x['id'], $x['igg'], $x['user'], $x['application'], $x['role'], $x['max_risk'],
                collect($x['conflicts'])->map(fn ($c) => "{$c['role']} ({$c['risk']})")->implode('; '),
                $x['justification'], $x['requester'], $x['approved_at'],
                collect($x['approvers'])->map(fn ($a) => $a['name'].($a['comments'] ? ": {$a['comments']}" : ''))->implode(' | '),
            ];
        });

        return $this->csv('excepciones_'.now()->format('Ymd_His').'.csv', [
            'Solicitud', 'IGG', 'Usuario', 'Aplicación', 'Rol otorgado', 'Riesgo máximo',
            'Conflictos aceptados', 'Justificación', 'Solicitante', 'Aprobada', 'Aprobadores y comentarios',
        ], $rows);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'application_id' => $request->integer('application_id') ?: null,
            'risk_level' => in_array($request->string('risk_level')->toString(), ['alto', 'medio', 'bajo'], true)
                ? $request->string('risk_level')->toString() : null,
            'date_from' => $request->date('date_from')?->format('Y-m-d'),
            'date_to' => $request->date('date_to')?->format('Y-m-d'),
        ];
    }

    /** @param  array<string, mixed>  $f */
    private function query(array $f): Builder
    {
        return RoleRequest::query()
            ->where('status', RequestStatus::Aprobado)
            ->where('has_sod_conflict', true)
            ->with([
                'managedUser:id,name,igg',
                'requestedRole:id,name,application_id', 'requestedRole.application:id,name',
                'requester:id,name',
                'findings.conflictingRole:id,name',
                'approvals.resolver:id,name',
            ])
            ->when($f['search'], fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('id', ltrim($s, '#'))
                ->orWhereHas('managedUser', fn ($m) => $m->where('name', 'like', "%{$s}%")->orWhere('igg', 'like', "%{$s}%"))))
            ->when($f['application_id'], fn ($q, $v) => $q->whereHas('requestedRole', fn ($r) => $r->where('application_id', $v)))
            ->when($f['risk_level'], fn ($q, $v) => $q->whereHas('findings', fn ($x) => $x->where('risk_level', $v)))
            ->when($f['date_from'], fn ($q, $v) => $q->whereHas('approvals', fn ($a) => $a->where('status', 'aprobado')->whereDate('resolved_at', '>=', $v)))
            ->when($f['date_to'], fn ($q, $v) => $q->whereHas('approvals', fn ($a) => $a->where('status', 'aprobado')->whereDate('resolved_at', '<=', $v)))
            ->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    private function row(RoleRequest $r): array
    {
        $approved = $r->approvals->where('status', ApprovalStatus::Aprobado);
        $maxRisk = $r->findings->sortByDesc(fn ($f) => self::RISK_ORDER[$f->risk_level] ?? 0)->first()?->risk_level;

        return [
            'id' => $r->id,
            'igg' => $r->managedUser?->igg,
            'user' => $r->managedUser?->name,
            'application' => $r->requestedRole?->application?->name,
            'role' => $r->requestedRole?->name,
            'max_risk' => $maxRisk,
            'conflicts' => $r->findings->map(fn ($f) => ['role' => $f->conflictingRole?->name, 'risk' => $f->risk_level])->values()->all(),
            'justification' => $r->justification,
            'requester' => $r->requester?->name,
            'approved_at' => $approved->max('resolved_at')?->format('Y-m-d H:i'),
            'approvers' => $approved->map(fn ($a) => [
                'name' => $a->resolver?->name ?? $a->approver?->name ?? '—',
                'role' => $a->approver_role,
                'comments' => $a->comments,
            ])->values()->all(),
        ];
    }
}
