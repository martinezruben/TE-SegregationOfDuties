<?php

namespace App\Services;

use App\Models\AuthorizationRule;
use App\Models\ManagedUser;
use App\Models\Role;
use Illuminate\Support\Collection;

/**
 * Resuelve la matriz de autorizaciones configurable (context.md §8.5).
 *
 * A partir de la evaluación SoD de una solicitud, determina qué reglas se
 * disparan (por criticidad de la matriz, por aplicación, o por desalineación
 * con el cargo) y CONSOLIDA los autorizadores: si un mismo autorizador cubre
 * varios disparadores, se genera un solo paso de autorización que los agrupa.
 *
 * "Su supervisor" se resuelve al jefe del usuario gestionado; si no tiene, se
 * marca `supervisor_missing` para avisar en la simulación.
 */
class AuthorizationResolver
{
    /**
     * @param  array<string, mixed>  $eval  Resultado de SodEvaluator::evaluate()
     * @return array{
     *   steps: list<array{key:string, authorizer_type:string, authorizer_id:?int, reasons:list<string>}>,
     *   supervisor_required: bool,
     *   supervisor_missing: bool,
     *   matched: bool
     * }
     */
    public function resolve(ManagedUser $user, Role $role, array $eval): array
    {
        $rules = AuthorizationRule::with('steps')->where('is_active', true)->get();
        $appId = $role->application_id;
        $supervisorId = $user->supervisor_id;

        /** @var Collection<string, array{key:string, authorizer_type:string, authorizer_id:?int, reasons:list<string>}> $groups */
        $groups = collect();
        $supervisorRequired = false;

        // Cada regla aporta sus pasos en orden; los pasos de distintas reglas
        // con el mismo autorizador se consolidan en uno (el orden es el de la
        // primera aparición).
        $add = function (AuthorizationRule $rule, string $reason) use (&$groups, &$supervisorRequired, $supervisorId): void {
            foreach ($rule->steps as $step) {
                $isSupervisor = $step->authorizer_type === 'supervisor';
                $authId = $isSupervisor ? $supervisorId : $step->authorizer_id;

                if ($isSupervisor) {
                    $supervisorRequired = true;
                    $key = 'supervisor'; // consolida a un solo paso del jefe
                } else {
                    $key = 'user:'.($authId ?? 'null:'.$step->id);
                }

                $existing = $groups->get($key);
                if ($existing === null) {
                    $groups->put($key, [
                        'key' => $key,
                        'authorizer_type' => $isSupervisor ? 'supervisor' : 'fixed',
                        'authorizer_id' => $authId,
                        'reasons' => [$reason],
                    ]);
                } elseif (! in_array($reason, $existing['reasons'], true)) {
                    $existing['reasons'][] = $reason;
                    $groups->put($key, $existing);
                }
            }
        };

        // 1) Por aplicación.
        $rules->where('trigger_type', 'application')
            ->where('application_id', $appId)
            ->each(fn (AuthorizationRule $r) => $add($r, 'Aplicación: '.($role->application?->name ?? '—')));

        // 2) Por desalineación con el cargo.
        if (! ($eval['cargo']['allowed'] ?? true)) {
            $rules->where('trigger_type', 'cargo_misalignment')
                ->filter(fn (AuthorizationRule $r) => $r->application_id === null || $r->application_id === $appId)
                ->each(fn (AuthorizationRule $r) => $add($r, 'Desalineación con el cargo'));
        }

        // 3) Por criticidad de la matriz (riesgo del conflicto SoD).
        $risks = collect($eval['conflicts'] ?? [])->pluck('risk_level')->filter()->unique();
        foreach ($risks as $risk) {
            $rules->where('trigger_type', 'sod_risk')
                ->where('risk_level', $risk)
                ->filter(fn (AuthorizationRule $r) => $r->application_id === null || $r->application_id === $appId)
                ->each(fn (AuthorizationRule $r) => $add($r, "Conflicto SoD (riesgo {$risk})"));
        }

        $steps = $groups->values()->all();

        return [
            'steps' => $steps,
            'supervisor_required' => $supervisorRequired,
            'supervisor_missing' => $supervisorRequired && $supervisorId === null,
            'matched' => count($steps) > 0,
        ];
    }
}
