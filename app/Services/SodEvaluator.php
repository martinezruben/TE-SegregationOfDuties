<?php

namespace App\Services;

use App\Models\ManagedUser;
use App\Models\Role;
use App\Models\SodConflict;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Motor de Segregación de Funciones (context.md §8.3 / §7.2.1).
 *
 * Evalúa la viabilidad de asignar un rol a un usuario gestionado:
 *  - Regla 1 (Cargo): valida contra job_position_role del cargo específico
 *    y su cargo genérico equivalente (no_permitido > permitido > sin_regla).
 *  - Regla 2 (SoD): cruza el rol nuevo contra la "foto" del usuario usando
 *    la matriz de conflictos.
 *
 * Servicio puro y testeable de forma aislada, sin dependencias de UI.
 */
class SodEvaluator
{
    /**
     * @return array{
     *   cargo: array{status: string, allowed: bool},
     *   conflicts: list<array{role_id:int, role_name:string, risk_level:string, description:?string}>,
     *   has_conflict: bool,
     *   already_assigned: bool,
     *   viable: bool
     * }
     */
    public function evaluate(ManagedUser $user, Role $role): array
    {
        $cargo = $this->evaluateCargo($user, $role);
        $conflicts = $this->evaluateConflicts($user, $role);
        $alreadyAssigned = $this->currentRoleIds($user)->contains($role->id);

        $hasConflict = count($conflicts) > 0;

        return [
            'cargo' => $cargo,
            'conflicts' => $conflicts,
            'has_conflict' => $hasConflict,
            'already_assigned' => $alreadyAssigned,
            'viable' => $cargo['allowed'] && ! $hasConflict && ! $alreadyAssigned,
        ];
    }

    /**
     * Regla de cargo resolviendo específico + genérico.
     *
     * @return array{status: string, allowed: bool}
     */
    private function evaluateCargo(ManagedUser $user, Role $role): array
    {
        $user->loadMissing('jobPosition');
        $positionIds = array_filter([
            $user->job_position_id,
            $user->jobPosition?->generic_position_id,
        ]);

        $rules = DB::table('job_position_role')
            ->where('role_id', $role->id)
            ->whereIn('job_position_id', $positionIds)
            ->pluck('rule_type');

        if ($rules->contains('no_permitido')) {
            return ['status' => 'no_permitido', 'allowed' => false];
        }

        if ($rules->contains('permitido')) {
            return ['status' => 'permitido', 'allowed' => true];
        }

        // Política por defecto: no permitido salvo regla explícita.
        return ['status' => 'sin_regla', 'allowed' => false];
    }

    /**
     * @return list<array{role_id:int, role_name:string, risk_level:string, description:?string}>
     */
    private function evaluateConflicts(ManagedUser $user, Role $role): array
    {
        $currentIds = $this->currentRoleIds($user)->reject(fn ($id) => $id === $role->id);

        if ($currentIds->isEmpty()) {
            return [];
        }

        $rows = SodConflict::query()
            ->where('is_active', true)
            ->where(function ($q) use ($role, $currentIds) {
                $q->where(fn ($w) => $w->where('role_a_id', $role->id)->whereIn('role_b_id', $currentIds))
                    ->orWhere(fn ($w) => $w->where('role_b_id', $role->id)->whereIn('role_a_id', $currentIds));
            })
            ->with(['roleA:id,name', 'roleB:id,name'])
            ->get();

        return $rows->map(function (SodConflict $c) use ($role) {
            $other = $c->role_a_id === $role->id ? $c->roleB : $c->roleA;

            return [
                'role_id' => $other?->id,
                'role_name' => $other?->name ?? '—',
                'risk_level' => $c->risk_level->value,
                'description' => $c->description,
            ];
        })->all();
    }

    /**
     * IDs de roles vigentes en la "foto" del usuario (excluye expirados).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function currentRoleIds(ManagedUser $user): \Illuminate\Support\Collection
    {
        return DB::table('user_role')
            ->where('managed_user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->pluck('role_id');
    }
}
