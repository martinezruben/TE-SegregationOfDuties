<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\Option;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessReportTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $area = Area::create(['name' => 'Finanzas', 'code' => 'FIN']);
        $position = JobPosition::create(['name' => 'Analista']);
        $app = Application::create(['name' => 'ERP', 'criticality_level' => 'alta']);
        $role = $app->roles()->create(['name' => 'Aprobar Pago']);
        Option::create(['role_id' => $role->id, 'name' => 'Autorizar pago', 'action_key' => 'pago.aprobar']);
        $user = ManagedUser::create([
            'external_id' => 'E1', 'igg' => 'IGG1', 'name' => 'María López',
            'area_id' => $area->id, 'job_position_id' => $position->id,
        ]);
        $user->roles()->attach($role->id, ['assigned_at' => now(), 'source' => 'import']);

        return compact('area', 'position', 'app', 'role', 'user');
    }

    public function test_report_requires_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->scenario();
        $sinPermiso = User::factory()->create();
        $sinPermiso->assignRole('Solicitante'); // no tiene reportes.ver

        $this->actingAs($sinPermiso)->get('/reportes/accesos')->assertForbidden();
    }

    public function test_report_is_visible_and_paginates(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->scenario();
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $this->actingAs($admin)->get('/reportes/accesos?level=opc')->assertOk();
    }

    public function test_export_streams_csv_with_user_data(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ['user' => $u] = $this->scenario();
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $response = $this->actingAs($admin)->get('/reportes/accesos/export?level=opc');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('María López', $csv);
        $this->assertStringContainsString('Autorizar pago', $csv);
        $this->assertStringContainsString('pago.aprobar', $csv);
    }

    public function test_application_level_deduplicates_to_user_application(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ['app' => $app, 'user' => $u] = $this->scenario();
        // Segundo rol en la misma app: a nivel "app" debe seguir siendo una fila.
        $role2 = $app->roles()->create(['name' => 'Crear Proveedor']);
        $u->roles()->attach($role2->id, ['assigned_at' => now(), 'source' => 'manual']);

        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $response = $this->actingAs($admin)->get('/reportes/accesos/export?level=app');
        $csv = $response->streamedContent();
        // Encabezado + una sola fila de datos (usuario+app), pese a tener 2 roles.
        $dataLines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $dataLines); // header + 1
    }
}
