<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsCsv;
use App\Models\Application;
use App\Models\Area;
use App\Models\CertificationCampaign;
use App\Models\CertificationItem;
use App\Models\User;
use App\Services\CertificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recertificación periódica de accesos: campañas, revisión (mantener / revocar),
 * cierre y exportación de evidencia.
 */
class CertificationController extends Controller
{
    use StreamsCsv;

    public function __construct(private CertificationService $service) {}

    /** ¿Ve todas las campañas e ítems (gestión o auditoría)? */
    private function seesAll(User $user): bool
    {
        return $user->can('certificaciones.crear') || $user->can('certificaciones.auditar');
    }

    private function authorizeView(Request $request, ?CertificationCampaign $campana = null): void
    {
        $user = $request->user();
        abort_unless((bool) $user?->can('certificaciones.ver'), 403);

        if ($campana && ! $this->seesAll($user)) {
            abort_unless($campana->items()->where('reviewer_id', $user->id)->exists(), 403);
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $user = $request->user();

        $campanas = CertificationCampaign::query()
            ->withCount([
                'items as total',
                'items as pendientes' => fn ($q) => $q->where('decision', 'pendiente'),
                'items as mantenidos' => fn ($q) => $q->where('decision', 'mantener'),
                'items as revocados' => fn ($q) => $q->where('decision', 'revocar'),
                'items as mis_pendientes' => fn ($q) => $q->where('decision', 'pendiente')->where('reviewer_id', $user->id),
            ])
            ->when(! $this->seesAll($user), fn ($q) => $q->whereHas('items', fn ($i) => $i->where('reviewer_id', $user->id)))
            ->latest('id')->limit(50)->get()
            ->map(fn (CertificationCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'due_date' => $c->due_date->format('Y-m-d'),
                'overdue' => $c->isOpen() && $c->due_date->isPast() && ! $c->due_date->isToday(),
                'total' => $c->total, 'pendientes' => $c->pendientes,
                'mantenidos' => $c->mantenidos, 'revocados' => $c->revocados,
                'mis_pendientes' => $c->mis_pendientes,
            ]);

        $canCreate = (bool) $user->can('certificaciones.crear');

        return Inertia::render('Certificaciones/Index', [
            'campanas' => $campanas,
            'can' => ['create' => $canCreate],
            'opciones' => $canCreate ? [
                'aplicaciones' => Application::orderBy('name')->get(['id', 'name']),
                'areas' => Area::orderBy('name')->get(['id', 'name']),
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('certificaciones.crear'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'application_id' => ['nullable', 'exists:applications,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'only_conflicts' => ['boolean'],
        ]);

        $campana = $this->service->createCampaign($data, $request->user());
        $this->service->notifyReviewers($campana);

        return redirect()->route('certificaciones.show', $campana)
            ->with('success', "Campaña creada con {$campana->items()->count()} acceso(s) por certificar.");
    }

    public function show(Request $request, CertificationCampaign $campana): Response
    {
        $this->authorizeView($request, $campana);
        $user = $request->user();
        $all = $this->seesAll($user);

        $status = in_array($request->string('decision')->toString(), ['pendiente', 'mantener', 'revocar'], true)
            ? $request->string('decision')->toString() : null;
        $reviewerId = $all ? ($request->integer('reviewer_id') ?: null) : $user->id;
        $search = trim((string) $request->string('search'));

        $scope = fn () => $campana->items()->when(! $all, fn ($q) => $q->where('reviewer_id', $user->id));

        $items = $scope()
            ->with(['reviewer:id,name', 'decider:id,name'])
            ->when($status, fn ($q, $v) => $q->where('decision', $v))
            ->when($reviewerId, fn ($q, $v) => $q->where('reviewer_id', $v))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('user_name', 'like', "%{$search}%")->orWhere('user_igg', 'like', "%{$search}%")
                ->orWhere('role_name', 'like', "%{$search}%")->orWhere('app_name', 'like', "%{$search}%")))
            ->orderByRaw("CASE conflict_risk WHEN 'alto' THEN 0 WHEN 'medio' THEN 1 WHEN 'bajo' THEN 2 ELSE 3 END")
            ->orderBy('user_name')->orderBy('id')
            ->paginate(25)->withQueryString()
            ->through(fn (CertificationItem $i) => [
                'id' => $i->id,
                'user_name' => $i->user_name, 'user_igg' => $i->user_igg,
                'area' => $i->area, 'cargo' => $i->cargo,
                'app_name' => $i->app_name, 'role_name' => $i->role_name,
                'source' => $i->source, 'assigned_at' => $i->assigned_at?->format('Y-m-d'),
                'expires_at' => $i->expires_at?->format('Y-m-d'),
                'conflict_risk' => $i->conflict_risk,
                'reviewer' => $i->reviewer?->name,
                'decision' => $i->decision,
                'decided_by' => $i->decider?->name,
                'decided_at' => $i->decided_at?->format('Y-m-d H:i'),
                'comments' => $i->comments,
                'can_decide' => $campana->isOpen() && $i->decision === 'pendiente'
                    && $i->reviewer_id === $user->id && $user->can('certificaciones.revisar'),
            ]);

        $counts = $scope()->selectRaw('decision, COUNT(*) as n')->groupBy('decision')->pluck('n', 'decision');

        return Inertia::render('Certificaciones/Show', [
            'campana' => [
                'id' => $campana->id, 'name' => $campana->name, 'status' => $campana->status,
                'due_date' => $campana->due_date->format('Y-m-d'),
                'overdue' => $campana->isOpen() && $campana->due_date->isPast() && ! $campana->due_date->isToday(),
                'filters' => $campana->filters,
                'closed_at' => $campana->closed_at?->format('Y-m-d H:i'),
                'creator' => $campana->creator?->name,
            ],
            'items' => $items,
            'filters' => ['decision' => $status, 'reviewer_id' => $all ? $reviewerId : null, 'search' => $search],
            'resumen' => [
                'total' => (int) $counts->sum(),
                'pendiente' => (int) ($counts['pendiente'] ?? 0),
                'mantener' => (int) ($counts['mantener'] ?? 0),
                'revocar' => (int) ($counts['revocar'] ?? 0),
            ],
            'revisores' => $all ? $campana->items()
                ->selectRaw('reviewer_id, COUNT(*) as total, SUM(CASE WHEN decision = \'pendiente\' THEN 1 ELSE 0 END) as pendientes')
                ->groupBy('reviewer_id')->get()
                ->map(fn ($r) => [
                    'id' => $r->reviewer_id,
                    'name' => $r->reviewer_id ? (User::find($r->reviewer_id)?->name ?? '—') : 'Sin revisor',
                    'total' => (int) $r->total, 'pendientes' => (int) $r->pendientes,
                ])->sortByDesc('pendientes')->values() : [],
            'can' => [
                'review' => (bool) $user->can('certificaciones.revisar'),
                'close' => $campana->isOpen() && $user->can('certificaciones.crear'),
                'export' => $all,
                'seeAll' => $all,
            ],
        ]);
    }

    public function decide(Request $request, CertificationCampaign $campana): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('certificaciones.revisar'), 403);
        $this->authorizeView($request, $campana);

        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1', 'max:500'],
            'item_ids.*' => ['integer'],
            'decision' => ['required', 'in:mantener,revocar'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $res = $this->service->decide($campana, $data['item_ids'], $data['decision'], $data['comments'] ?? null, $request->user());

        if ($res['decididos'] === 0) {
            return back()->with('error', 'Ningún acceso seleccionado estaba pendiente y asignado a ti.');
        }

        $verbo = $data['decision'] === 'revocar' ? 'revocado(s) y retirado(s) de la foto' : 'mantenido(s)';

        return back()->with('success', "{$res['decididos']} acceso(s) {$verbo}.".($res['omitidos'] ? " {$res['omitidos']} omitido(s) (no eran tuyos o ya estaban resueltos)." : ''));
    }

    public function close(Request $request, CertificationCampaign $campana): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('certificaciones.crear'), 403);

        $this->service->close($campana, $request->user());

        return back()->with('success', 'Campaña cerrada. Los accesos sin respuesta quedan registrados como pendientes.');
    }

    public function export(Request $request, CertificationCampaign $campana): StreamedResponse
    {
        abort_unless($this->seesAll($request->user()) && (bool) $request->user()->can('certificaciones.ver'), 403);

        $rows = $campana->items()->with(['reviewer:id,name', 'decider:id,name'])->orderBy('user_name')->cursor()
            ->map(fn (CertificationItem $i) => [
                $i->user_igg, $i->user_name, $i->area, $i->cargo, $i->app_name, $i->role_name, $i->source,
                $i->assigned_at?->format('Y-m-d'), $i->expires_at?->format('Y-m-d'), $i->conflict_risk,
                $i->reviewer?->name, $i->decision, $i->decider?->name, $i->decided_at?->format('Y-m-d H:i'), $i->comments,
            ]);

        return $this->csv("recertificacion_{$campana->id}_".now()->format('Ymd').'.csv', [
            'IGG', 'Usuario', 'Área', 'Cargo', 'Aplicación', 'Rol', 'Origen', 'Asignado', 'Expira', 'Riesgo SoD',
            'Revisor', 'Decisión', 'Decidido por', 'Fecha decisión', 'Comentario',
        ], $rows);
    }
}
