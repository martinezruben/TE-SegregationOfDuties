<?php

namespace App\Services\Import;

use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Importa/actualiza usuarios gestionados (clave de cruce: IGG).
 *
 * Columnas: id | igg | nombre | area (código o nombre) | cargo | supervisor_email (opc) | activo (opc)
 */
class ManagedUserImporter implements Importer
{
    public function kind(): string
    {
        return 'gestionados';
    }

    public function label(): string
    {
        return 'Usuarios gestionados';
    }

    public function permission(): string
    {
        return 'gestionados.crear';
    }

    public function template(): array
    {
        return [
            'headers' => ['id', 'igg', 'nombre', 'area', 'cargo', 'supervisor_email', 'activo'],
            'example' => ['E-1001', 'IGG001', 'María Pérez', 'FIN', 'Analista', 'jefe@empresa.com', 'si'],
        ];
    }

    public function analyze(array $rows): array
    {
        $areas = Area::all(['id', 'name', 'code']);
        $areaByKey = [];
        foreach ($areas as $a) {
            $areaByKey[Str::lower($a->code)] = $a;
            $areaByKey[Str::lower($a->name)] ??= $a;
        }
        $positions = JobPosition::all(['id', 'name'])->keyBy(fn ($p) => Str::lower($p->name));
        $agents = User::all(['id', 'email'])->keyBy(fn ($u) => Str::lower($u->email));
        $byIgg = ManagedUser::withTrashed()->get()->keyBy(fn ($u) => Str::lower($u->igg));
        $byExternal = ManagedUser::withTrashed()->get()->keyBy(fn ($u) => Str::lower($u->external_id));

        $seenIgg = [];
        $seenExt = [];
        $out = [];

        foreach ($rows as ['line' => $line, 'data' => $d]) {
            $externalId = (string) SpreadsheetReader::pick($d, 'id', 'external_id', 'id_externo');
            $igg = (string) SpreadsheetReader::pick($d, 'igg');
            $name = (string) SpreadsheetReader::pick($d, 'nombre', 'name');
            $areaName = (string) SpreadsheetReader::pick($d, 'area');
            $cargoName = (string) SpreadsheetReader::pick($d, 'cargo');
            $email = (string) SpreadsheetReader::pick($d, 'supervisor_email', 'supervisor');
            $label = trim("{$name} ({$igg})");

            $err = fn (string $m) => ['line' => $line, 'status' => 'error', 'label' => $label, 'message' => $m, 'plan' => null];

            foreach (['id' => $externalId, 'igg' => $igg, 'nombre' => $name, 'area' => $areaName, 'cargo' => $cargoName] as $col => $val) {
                if ($val === '') {
                    $out[] = $err("Falta la columna obligatoria «{$col}».");

                    continue 2;
                }
            }

            $area = $areaByKey[Str::lower($areaName)] ?? null;
            if (! $area) {
                $out[] = $err("Área «{$areaName}» no existe (usa el código o el nombre).");

                continue;
            }
            $position = $positions[Str::lower($cargoName)] ?? null;
            if (! $position) {
                $out[] = $err("Cargo «{$cargoName}» no existe.");

                continue;
            }
            $supervisorId = null;
            if ($email !== '') {
                $supervisor = $agents[Str::lower($email)] ?? null;
                if (! $supervisor) {
                    $out[] = $err("No existe un agente con el correo «{$email}» para supervisor.");

                    continue;
                }
                $supervisorId = $supervisor->id;
            }

            $iggKey = Str::lower($igg);
            $extKey = Str::lower($externalId);
            if (isset($seenIgg[$iggKey])) {
                $out[] = $err("IGG duplicado en el archivo (fila {$seenIgg[$iggKey]}).");

                continue;
            }
            if (isset($seenExt[$extKey])) {
                $out[] = $err("ID duplicado en el archivo (fila {$seenExt[$extKey]}).");

                continue;
            }

            $existing = $byIgg[$iggKey] ?? null;
            $otherByExt = $byExternal[$extKey] ?? null;
            if ($otherByExt && (! $existing || $otherByExt->id !== $existing->id)) {
                $out[] = $err("El ID «{$externalId}» ya pertenece a otro usuario gestionado.");

                continue;
            }
            if ($existing?->trashed()) {
                $out[] = $err('El usuario existe pero está eliminado; restáuralo antes de importar.');

                continue;
            }

            $seenIgg[$iggKey] = $line;
            $seenExt[$extKey] = $line;

            $plan = [
                'external_id' => $externalId,
                'igg' => $igg,
                'name' => $name,
                'area_id' => $area->id,
                'job_position_id' => $position->id,
                'supervisor_id' => $supervisorId,
                'is_active' => SpreadsheetReader::bool(SpreadsheetReader::pick($d, 'activo', 'is_active'), default: $existing?->is_active ?? true),
                'existing_id' => $existing?->id,
            ];

            if ($existing === null) {
                $out[] = ['line' => $line, 'status' => 'crear', 'label' => $label, 'message' => null, 'plan' => $plan];

                continue;
            }

            $changes = collect(['external_id', 'name', 'area_id', 'job_position_id', 'is_active'])
                ->filter(fn ($f) => $existing->{$f} != $plan[$f])
                ->when($email !== '' && $existing->supervisor_id != $supervisorId, fn ($c) => $c->push('supervisor_id'))
                ->values();

            $out[] = $changes->isEmpty()
                ? ['line' => $line, 'status' => 'sin_cambios', 'label' => $label, 'message' => 'Sin cambios.', 'plan' => $plan]
                : ['line' => $line, 'status' => 'actualizar', 'label' => $label, 'message' => 'Cambia: '.$changes->implode(', ').'.', 'plan' => $plan];
        }

        return $out;
    }

    public function apply(array $analysis, User $by): array
    {
        $res = ['creados' => 0, 'actualizados' => 0, 'sin_cambios' => 0];

        foreach ($analysis as $row) {
            $p = $row['plan'];
            $data = collect($p)->only(['external_id', 'igg', 'name', 'area_id', 'job_position_id', 'is_active'])->all();

            if ($row['status'] === 'crear') {
                ManagedUser::create($data + ['supervisor_id' => $p['supervisor_id']]);
                $res['creados']++;
            } elseif ($row['status'] === 'actualizar') {
                $user = ManagedUser::findOrFail($p['existing_id']);
                // Sin columna supervisor_email no se pisa el supervisor actual.
                $user->update($data + ($p['supervisor_id'] !== null ? ['supervisor_id' => $p['supervisor_id']] : []));
                $res['actualizados']++;
            } elseif ($row['status'] === 'sin_cambios') {
                $res['sin_cambios']++;
            }
        }

        return $res;
    }
}
