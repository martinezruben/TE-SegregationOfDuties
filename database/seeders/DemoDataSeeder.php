<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\Option;
use App\Models\Role;
use App\Models\SodConflict;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos demo para poder navegar el sistema (context.md §19).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->orderBy('id')->first();

        // Áreas
        $finanzas = Area::create(['name' => 'Finanzas', 'code' => 'FIN']);
        $compras = Area::create(['name' => 'Compras', 'code' => 'COM']);
        $ti = Area::create(['name' => 'Tecnología', 'code' => 'TI']);

        // Cargos genéricos + específicos
        $genAnalista = JobPosition::create(['name' => 'Analista (genérico)', 'is_generic' => true]);
        $genJefe = JobPosition::create(['name' => 'Jefatura (genérico)', 'is_generic' => true]);

        $analistaFin = JobPosition::create(['name' => 'Analista Financiero', 'generic_position_id' => $genAnalista->id]);
        $analistaCom = JobPosition::create(['name' => 'Analista de Compras', 'generic_position_id' => $genAnalista->id]);
        $jefeFin = JobPosition::create(['name' => 'Jefe de Finanzas', 'generic_position_id' => $genJefe->id]);

        // Aplicación ERP Finanzas (criticidad alta) con roles y opciones
        $erp = Application::create([
            'name' => 'ERP Finanzas',
            'description' => 'Sistema financiero corporativo',
            'criticality_level' => 'alta',
            'owner_id' => $owner?->id,
        ]);

        $crearProveedor = Role::create(['application_id' => $erp->id, 'name' => 'Crear Proveedor']);
        $aprobarPago = Role::create(['application_id' => $erp->id, 'name' => 'Aprobar Pago']);
        $consultarFin = Role::create(['application_id' => $erp->id, 'name' => 'Consultar Finanzas']);

        Option::create(['role_id' => $crearProveedor->id, 'name' => 'Alta de proveedor', 'action_key' => 'proveedor.crear']);
        Option::create(['role_id' => $aprobarPago->id, 'name' => 'Autorizar pago', 'action_key' => 'pago.aprobar']);
        Option::create(['role_id' => $consultarFin->id, 'name' => 'Ver reportes', 'action_key' => 'finanzas.ver']);

        // Aplicación Compras (criticidad media)
        $comprasApp = Application::create([
            'name' => 'Portal de Compras',
            'description' => 'Gestión de órdenes de compra',
            'criticality_level' => 'media',
            'owner_id' => $owner?->id,
        ]);
        $crearOrden = Role::create(['application_id' => $comprasApp->id, 'name' => 'Crear Orden']);
        $aprobarOrden = Role::create(['application_id' => $comprasApp->id, 'name' => 'Aprobar Orden']);

        // Matriz SoD: conflicto clásico Crear Proveedor <-> Aprobar Pago (alto)
        // y Crear Orden <-> Aprobar Orden (medio). Convención role_a_id < role_b_id.
        $this->conflict($crearProveedor->id, $aprobarPago->id, 'alto', 'Quien crea proveedores no debe aprobar sus pagos.');
        $this->conflict($crearOrden->id, $aprobarOrden->id, 'medio', 'Quien crea la orden no debe aprobarla.');

        // Reglas de cargo (permitido / no permitido)
        $jefeFin->roleRules()->attach($aprobarPago->id, ['rule_type' => 'permitido']);
        $analistaFin->roleRules()->attach($crearProveedor->id, ['rule_type' => 'permitido']);
        $analistaFin->roleRules()->attach($aprobarPago->id, ['rule_type' => 'no_permitido', 'notes' => 'Segregación por cargo']);
        $genAnalista->roleRules()->attach($consultarFin->id, ['rule_type' => 'permitido']);

        // Usuarios gestionados (población gobernada)
        $u1 = ManagedUser::create([
            'external_id' => 'EMP-10245', 'igg' => 'IGG10245', 'name' => 'María López',
            'area_id' => $finanzas->id, 'job_position_id' => $analistaFin->id,
        ]);
        $u2 = ManagedUser::create([
            'external_id' => 'EMP-10388', 'igg' => 'IGG10388', 'name' => 'Juan Pérez',
            'area_id' => $compras->id, 'job_position_id' => $analistaCom->id,
        ]);
        ManagedUser::create([
            'external_id' => 'EMP-10502', 'igg' => 'IGG10502', 'name' => 'Ana Ruiz',
            'area_id' => $ti->id, 'job_position_id' => $jefeFin->id,
        ]);

        // "Foto" inicial: María ya tiene Crear Proveedor
        $u1->roles()->attach($crearProveedor->id, [
            'assigned_at' => now(), 'assigned_by' => $owner?->id, 'source' => 'import',
        ]);
        $u2->roles()->attach($crearOrden->id, [
            'assigned_at' => now(), 'assigned_by' => $owner?->id, 'source' => 'import',
        ]);
    }

    private function conflict(int $a, int $b, string $risk, string $desc): void
    {
        SodConflict::create([
            'role_a_id' => min($a, $b),
            'role_b_id' => max($a, $b),
            'risk_level' => $risk,
            'description' => $desc,
        ]);
    }
}
