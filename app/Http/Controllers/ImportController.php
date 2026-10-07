<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsCsv;
use App\Models\ImportBatch;
use App\Services\Import\FotoImporter;
use App\Services\Import\Importer;
use App\Services\Import\ManagedUserImporter;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importación masiva con dry-run: subir → previsualizar (sin escribir) → confirmar.
 * Ver context.md §8.1 y §14.
 */
class ImportController extends Controller
{
    use StreamsCsv;

    private const PREVIEW_ROWS = 200;

    private const MAX_ERRORS = 500;

    /** @return array<string, Importer> */
    private function importers(): array
    {
        return collect([app(ManagedUserImporter::class), app(FotoImporter::class)])
            ->keyBy(fn (Importer $i) => $i->kind())
            ->all();
    }

    private function importer(string $kind): Importer
    {
        return $this->importers()[$kind] ?? abort(404);
    }

    private function authorizeKind(Request $request, Importer $importer, string $ability = 'importaciones.crear'): void
    {
        abort_unless((bool) $request->user()?->can($ability) && (bool) $request->user()->can($importer->permission()), 403);
    }

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('importaciones.ver'), 403);

        $user = $request->user();

        return Inertia::render('Importaciones/Index', [
            'tipos' => collect($this->importers())
                ->map(fn (Importer $i) => [
                    'kind' => $i->kind(),
                    'label' => $i->label(),
                    'columns' => $i->template()['headers'],
                    'allowed' => $user->can('importaciones.crear') && $user->can($i->permission()),
                ])->values(),
            'lotes' => ImportBatch::with('user:id,name')
                ->latest('id')->limit(10)->get()
                ->map(fn (ImportBatch $b) => [
                    'id' => $b->id,
                    'kind' => $b->kind,
                    'filename' => $b->filename,
                    'status' => $b->status,
                    'total_rows' => $b->total_rows,
                    'user' => $b->user?->name,
                    'created_at' => $b->created_at?->format('Y-m-d H:i'),
                ]),
        ]);
    }

    /** Paso 1: analiza el archivo SIN escribir datos y guarda el lote para confirmar. */
    public function preview(Request $request, SpreadsheetReader $reader): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);

        $importer = $this->importer($data['kind']);
        $this->authorizeKind($request, $importer);

        try {
            $rows = $reader->read($request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'No se pudo leer el archivo. Verifica que sea un CSV o XLSX válido con fila de encabezados.']);
        }

        if ($rows === []) {
            return back()->withErrors(['file' => 'El archivo no tiene filas de datos.']);
        }
        if (count($rows) > SpreadsheetReader::MAX_ROWS) {
            return back()->withErrors(['file' => 'El archivo supera el máximo de '.SpreadsheetReader::MAX_ROWS.' filas. Divídelo en partes.']);
        }

        $analysis = $importer->analyze($rows);
        $errors = array_values(array_filter($analysis, fn ($r) => $r['status'] === 'error'));

        $batch = ImportBatch::create([
            'kind' => $importer->kind(),
            'user_id' => $request->user()->id,
            'filename' => $request->file('file')->getClientOriginalName(),
            'status' => 'preview',
            'total_rows' => count($rows),
            'summary' => $this->summarize($analysis),
            'preview' => array_map($this->displayRow(...), array_slice($analysis, 0, self::PREVIEW_ROWS)),
            'errors' => array_map($this->displayRow(...), array_slice($errors, 0, self::MAX_ERRORS)),
            'raw_rows' => $rows,
        ]);

        return redirect()->route('importaciones.show', $batch);
    }

    public function show(Request $request, ImportBatch $lote): Response
    {
        abort_unless((bool) $request->user()?->can('importaciones.ver'), 403);

        $importer = $this->importer($lote->kind);
        $user = $request->user();

        return Inertia::render('Importaciones/Show', [
            'lote' => [
                'id' => $lote->id,
                'kind' => $lote->kind,
                'label' => $importer->label(),
                'filename' => $lote->filename,
                'status' => $lote->status,
                'total_rows' => $lote->total_rows,
                'summary' => $lote->summary,
                'preview' => $lote->preview,
                'errors' => $lote->errors,
                'result' => $lote->result,
                'applied_at' => $lote->applied_at?->format('Y-m-d H:i'),
                'user' => $lote->user?->name,
                'preview_limit' => self::PREVIEW_ROWS,
            ],
            'canApply' => ! $lote->isApplied() && $user->can('importaciones.crear') && $user->can($importer->permission()),
        ]);
    }

    /** Paso 2: re-valida (los datos pudieron cambiar) y aplica solo las filas válidas, en una transacción. */
    public function apply(Request $request, ImportBatch $lote): RedirectResponse
    {
        $importer = $this->importer($lote->kind);
        $this->authorizeKind($request, $importer);

        $outcome = DB::transaction(function () use ($lote, $importer, $request) {
            $locked = ImportBatch::whereKey($lote->id)->lockForUpdate()->firstOrFail();
            if ($locked->isApplied()) {
                return 'applied';
            }

            $analysis = $importer->analyze($locked->raw_rows);
            $valid = array_values(array_filter($analysis, fn ($r) => $r['status'] !== 'error'));
            $changes = count(array_filter($valid, fn ($r) => in_array($r['status'], ['crear', 'actualizar'], true)));

            if ($changes === 0) {
                return 'nothing';
            }

            $result = $importer->apply($valid, $request->user());
            $result['errores'] = count($analysis) - count($valid);

            $locked->update([
                'status' => 'applied',
                'applied_at' => now(),
                'summary' => $this->summarize($analysis),
                'result' => $result,
                'raw_rows' => [], // ya no se necesitan; evita conservar datos innecesarios
            ]);

            return $result;
        });

        if ($outcome === 'applied') {
            return back()->with('error', 'Este lote ya fue aplicado.');
        }
        if ($outcome === 'nothing') {
            return back()->with('error', 'No hay filas válidas con cambios para aplicar.');
        }

        return redirect()->route('importaciones.show', $lote)->with(
            'success',
            "Importación aplicada: {$outcome['creados']} creados, {$outcome['actualizados']} actualizados, {$outcome['sin_cambios']} sin cambios, {$outcome['errores']} con error (omitidos).",
        );
    }

    public function template(Request $request, string $kind): StreamedResponse
    {
        abort_unless((bool) $request->user()?->can('importaciones.ver'), 403);

        $tpl = $this->importer($kind)->template();

        return $this->csv("plantilla_{$kind}.csv", $tpl['headers'], [$tpl['example']]);
    }

    public function errors(Request $request, ImportBatch $lote): StreamedResponse
    {
        abort_unless((bool) $request->user()?->can('importaciones.ver'), 403);

        return $this->csv(
            "errores_lote_{$lote->id}.csv",
            ['fila', 'registro', 'error'],
            array_map(fn ($e) => [$e['line'], $e['label'], $e['message']], $lote->errors ?? []),
        );
    }

    /** @param  list<array<string, mixed>>  $analysis */
    private function summarize(array $analysis): array
    {
        $counts = array_count_values(array_column($analysis, 'status'));

        return [
            'crear' => $counts['crear'] ?? 0,
            'actualizar' => $counts['actualizar'] ?? 0,
            'sin_cambios' => $counts['sin_cambios'] ?? 0,
            'error' => $counts['error'] ?? 0,
        ];
    }

    /** Fila para mostrar: sin el plan interno. */
    private function displayRow(array $row): array
    {
        return ['line' => $row['line'], 'status' => $row['status'], 'label' => $row['label'], 'message' => $row['message']];
    }
}
