<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;

/**
 * Bitácora: visor de la auditoría (context.md §10).
 */
class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('bitacora.ver'), 403);

        $search = (string) $request->string('search');

        $audits = Audit::query()
            ->with('user:id,name')
            ->when($search !== '', fn ($q) => $q
                ->where('event', 'like', "%{$search}%")
                ->orWhere('auditable_type', 'like', "%{$search}%"))
            ->latest()
            ->paginate(20)
            ->through(fn (Audit $a) => [
                'id' => $a->id,
                'event' => $a->event,
                'entity' => class_basename((string) $a->auditable_type),
                'entity_id' => $a->auditable_id,
                'user' => $a->user?->name ?? 'Sistema',
                'ip' => $a->ip_address,
                'changed' => collect($a->getModified())->keys()->take(6)->implode(', '),
                'created_at' => $a->created_at?->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('Bitacora/Index', [
            'audits' => $audits,
            'filters' => ['search' => $search],
        ]);
    }
}
