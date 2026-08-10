<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\Role;
use App\Models\RoleRequest;
use App\Models\SodConflict;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private User $security;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Admin']);
        $this->admin->assignRole('Administrador');
        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->owner->assignRole('Owner de Aplicación');
        $this->security = User::factory()->create(['name' => 'Seguridad']);
        $this->security->assignRole('Gestor de Seguridad');
    }

    private function scenario(string $criticality = 'media'): array
    {
        $area = Area::create(['name' => 'Fin', 'code' => 'FIN']);
        $position = JobPosition::create(['name' => 'Analista']);
        $app = Application::create(['name' => 'ERP', 'criticality_level' => $criticality, 'owner_id' => $this->owner->id]);
        $crear = $app->roles()->create(['name' => 'Crear Proveedor']);
        $aprobar = $app->roles()->create(['name' => 'Aprobar Pago']);
        $user = ManagedUser::create([
            'external_id' => 'E1', 'igg' => 'I1', 'name' => 'María',
            'area_id' => $area->id, 'job_position_id' => $position->id,
        ]);

        return compact('position', 'app', 'crear', 'aprobar', 'user');
    }

    public function test_consultor_cannot_create_request(): void
    {
        $consultor = User::factory()->create();
        $consultor->assignRole('Consultor');
        ['user' => $u, 'crear' => $role] = $this->scenario();

        $this->actingAs($consultor)
            ->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $role->id])
            ->assertForbidden();
    }

    public function test_clean_request_is_auto_approved_and_applies_foto(): void
    {
        ['position' => $p, 'crear' => $role, 'user' => $u] = $this->scenario();
        $p->roleRules()->attach($role->id, ['rule_type' => 'permitido']);

        $this->actingAs($this->admin)
            ->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $role->id])
            ->assertRedirect(route('solicitudes.index'));

        $req = RoleRequest::first();
        $this->assertSame(RequestStatus::AutoAprobado, $req->status);
        $this->assertTrue($req->auto_resolved);
        $this->assertDatabaseHas('user_role', ['managed_user_id' => $u->id, 'role_id' => $role->id]);
    }

    public function test_conflict_request_creates_security_step_and_records_findings(): void
    {
        ['position' => $p, 'crear' => $crear, 'aprobar' => $aprobar, 'user' => $u] = $this->scenario('media');
        $u->roles()->attach($crear->id, ['assigned_at' => now(), 'source' => 'import']);
        $p->roleRules()->attach($aprobar->id, ['rule_type' => 'permitido']);
        SodConflict::create(['role_a_id' => min($crear->id, $aprobar->id), 'role_b_id' => max($crear->id, $aprobar->id), 'risk_level' => 'alto']);

        $this->actingAs($this->admin)
            ->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $aprobar->id])
            ->assertRedirect();

        $req = RoleRequest::first();
        $this->assertSame(RequestStatus::EnAprobacion, $req->status);
        $this->assertTrue($req->has_sod_conflict);
        $this->assertDatabaseHas('request_sod_findings', ['role_request_id' => $req->id, 'conflicting_role_id' => $crear->id]);
        $this->assertTrue($req->approvals()->where('approver_role', 'seguridad')->exists());
        // Aún no se aplica la foto.
        $this->assertDatabaseMissing('user_role', ['managed_user_id' => $u->id, 'role_id' => $aprobar->id]);
    }

    public function test_full_approval_flow_applies_foto(): void
    {
        ['position' => $p, 'crear' => $crear, 'aprobar' => $aprobar, 'user' => $u] = $this->scenario('alta');
        $u->roles()->attach($crear->id, ['assigned_at' => now(), 'source' => 'import']);
        $p->roleRules()->attach($aprobar->id, ['rule_type' => 'permitido']);
        SodConflict::create(['role_a_id' => min($crear->id, $aprobar->id), 'role_b_id' => max($crear->id, $aprobar->id), 'risk_level' => 'alto']);

        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $aprobar->id]);
        $req = RoleRequest::first();

        // Paso 1: owner aprueba.
        $this->actingAs($this->owner)->post("/solicitudes/{$req->id}/aprobar")->assertRedirect();
        $this->assertSame(RequestStatus::EnAprobacion, $req->fresh()->status);

        // Paso 2: seguridad aprueba -> aprobado + foto.
        $this->actingAs($this->security)->post("/solicitudes/{$req->id}/aprobar")->assertRedirect();
        $this->assertSame(RequestStatus::Aprobado, $req->fresh()->status);
        $this->assertDatabaseHas('user_role', ['managed_user_id' => $u->id, 'role_id' => $aprobar->id]);
    }

    public function test_non_approver_cannot_approve(): void
    {
        ['position' => $p, 'aprobar' => $aprobar, 'user' => $u] = $this->scenario('media');
        // Sin regla -> no auto -> paso owner_app.
        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $aprobar->id]);
        $req = RoleRequest::first();

        // Seguridad NO es el aprobador del paso actual (owner_app).
        $this->actingAs($this->security)->post("/solicitudes/{$req->id}/aprobar")->assertForbidden();
    }

    public function test_reject_sets_status(): void
    {
        ['aprobar' => $aprobar, 'user' => $u] = $this->scenario('media');
        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $aprobar->id]);
        $req = RoleRequest::first();

        $this->actingAs($this->owner)->post("/solicitudes/{$req->id}/rechazar")->assertRedirect();
        $this->assertSame(RequestStatus::Rechazado, $req->fresh()->status);
    }
}
