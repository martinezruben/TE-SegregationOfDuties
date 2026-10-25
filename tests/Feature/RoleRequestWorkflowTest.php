<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Application;
use App\Models\Area;
use App\Models\AuthorizationRule;
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

    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<array{authorizer_type:string, authorizer_id:?int}>  $steps
     */
    private function rule(array $attrs, array $steps): AuthorizationRule
    {
        $rule = AuthorizationRule::create($attrs);
        $rule->syncSteps($steps);

        return $rule;
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
            ->assertRedirect();

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

    public function test_request_stores_validation_snapshot_in_payload(): void
    {
        ['position' => $p, 'crear' => $crear, 'aprobar' => $aprobar, 'user' => $u] = $this->scenario('alta');
        $u->roles()->attach($crear->id, ['assigned_at' => now(), 'source' => 'import']);
        $p->roleRules()->attach($aprobar->id, ['rule_type' => 'permitido']);
        SodConflict::create(['role_a_id' => min($crear->id, $aprobar->id), 'role_b_id' => max($crear->id, $aprobar->id), 'risk_level' => 'alto']);

        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $aprobar->id]);
        $req = RoleRequest::first();

        $this->assertSame('permitido', $req->payload['cargo']['status']);
        $this->assertTrue($req->payload['has_conflict']);
        $this->assertNotEmpty($req->payload['reasons']);
    }

    public function test_show_detail_is_visible_to_authorized_user(): void
    {
        ['aprobar' => $aprobar, 'user' => $u] = $this->scenario('media');
        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $aprobar->id]);
        $req = RoleRequest::first();

        $this->actingAs($this->admin)->get("/solicitudes/{$req->id}")->assertOk();
    }

    public function test_matrix_consolidates_same_authorizer_into_one_step(): void
    {
        ['position' => $p, 'crear' => $crear, 'aprobar' => $aprobar, 'user' => $u] = $this->scenario('media');
        // Dispara conflicto SoD (riesgo alto) y desalineación de cargo (sin regla).
        $u->roles()->attach($crear->id, ['assigned_at' => now(), 'source' => 'import']);
        SodConflict::create(['role_a_id' => min($crear->id, $aprobar->id), 'role_b_id' => max($crear->id, $aprobar->id), 'risk_level' => 'alto']);

        // Ambas reglas apuntan al MISMO autorizador (seguridad).
        $this->rule(['name' => 'R1', 'trigger_type' => 'sod_risk', 'risk_level' => 'alto'], [['authorizer_type' => 'fixed', 'authorizer_id' => $this->security->id]]);
        $this->rule(['name' => 'R2', 'trigger_type' => 'cargo_misalignment'], [['authorizer_type' => 'fixed', 'authorizer_id' => $this->security->id]]);

        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $aprobar->id]);
        $req = RoleRequest::first();

        // Un solo paso, aunque haya dos disparadores.
        $this->assertCount(1, $req->approvals);
        $step = $req->approvals->first();
        $this->assertSame($this->security->id, $step->approver_id);
        $this->assertCount(2, $step->reasons);
    }

    public function test_rule_with_multiple_steps_creates_sequential_approvals(): void
    {
        ['position' => $p, 'app' => $app, 'crear' => $role, 'user' => $u] = $this->scenario('media');
        $p->roleRules()->attach($role->id, ['rule_type' => 'permitido']);
        $u->update(['supervisor_id' => $this->owner->id]);

        // Primero el jefe, luego Seguridad.
        $this->rule(['name' => 'App→Jefe→Seguridad', 'trigger_type' => 'application', 'application_id' => $app->id], [
            ['authorizer_type' => 'supervisor', 'authorizer_id' => null],
            ['authorizer_type' => 'fixed', 'authorizer_id' => $this->security->id],
        ]);

        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $role->id]);
        $approvals = RoleRequest::first()->approvals()->orderBy('step_order')->get();

        $this->assertCount(2, $approvals);
        $this->assertSame([$this->owner->id, $this->security->id], $approvals->pluck('approver_id')->all());
        $this->assertSame([1, 2], $approvals->pluck('step_order')->all());
    }

    public function test_rules_crud_stores_ordered_steps_and_filters_by_authorizer(): void
    {
        $this->actingAs($this->admin);

        $this->post('/autorizaciones', [
            'name' => 'Dos pasos', 'trigger_type' => 'cargo_misalignment', 'is_active' => true,
            'steps' => [
                ['authorizer_type' => 'supervisor', 'authorizer_id' => null],
                ['authorizer_type' => 'fixed', 'authorizer_id' => $this->security->id],
            ],
        ])->assertSessionHasNoErrors();

        $rule = AuthorizationRule::first();
        $this->assertSame(['supervisor', 'fixed'], $rule->steps->pluck('authorizer_type')->all());

        // Paso fijo sin persona: error de validación.
        $this->post('/autorizaciones', [
            'name' => 'Mala', 'trigger_type' => 'cargo_misalignment', 'is_active' => true,
            'steps' => [['authorizer_type' => 'fixed', 'authorizer_id' => null]],
        ])->assertSessionHasErrors('steps.0.authorizer_id');

        $this->rule(['name' => 'Otra', 'trigger_type' => 'sod_risk', 'risk_level' => 'bajo'], [['authorizer_type' => 'fixed', 'authorizer_id' => $this->owner->id]]);

        $this->get('/autorizaciones?authorizer='.$this->security->id)
            ->assertInertia(fn ($page) => $page->has('reglas.data', 1)->where('reglas.data.0.name', 'Dos pasos'));
        $this->get('/autorizaciones?trigger_type=sod_risk&search=otr')
            ->assertInertia(fn ($page) => $page->has('reglas.data', 1)->where('reglas.data.0.name', 'Otra'));
    }

    public function test_supervisor_rule_resolves_to_managed_user_supervisor(): void
    {
        ['position' => $p, 'crear' => $crear, 'aprobar' => $aprobar, 'user' => $u] = $this->scenario('media');
        $u->update(['supervisor_id' => $this->owner->id]);
        $u->roles()->attach($crear->id, ['assigned_at' => now(), 'source' => 'import']);
        $p->roleRules()->attach($aprobar->id, ['rule_type' => 'permitido']); // cargo permitido: solo dispara el SoD
        SodConflict::create(['role_a_id' => min($crear->id, $aprobar->id), 'role_b_id' => max($crear->id, $aprobar->id), 'risk_level' => 'medio']);

        $this->rule(['name' => 'Medio→Jefe', 'trigger_type' => 'sod_risk', 'risk_level' => 'medio'], [['authorizer_type' => 'supervisor', 'authorizer_id' => null]]);

        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $aprobar->id]);
        $req = RoleRequest::first();

        $this->assertCount(1, $req->approvals);
        $this->assertSame($this->owner->id, $req->approvals->first()->approver_id);
        $this->assertSame('jefe', $req->approvals->first()->approver_role);
    }

    public function test_missing_supervisor_is_flagged_and_falls_back_to_security(): void
    {
        ['position' => $p, 'crear' => $crear, 'aprobar' => $aprobar, 'user' => $u] = $this->scenario('media');
        // Sin supervisor asignado.
        $u->roles()->attach($crear->id, ['assigned_at' => now(), 'source' => 'import']);
        $p->roleRules()->attach($aprobar->id, ['rule_type' => 'permitido']);
        SodConflict::create(['role_a_id' => min($crear->id, $aprobar->id), 'role_b_id' => max($crear->id, $aprobar->id), 'risk_level' => 'medio']);

        $this->rule(['name' => 'Medio→Jefe', 'trigger_type' => 'sod_risk', 'risk_level' => 'medio'], [['authorizer_type' => 'supervisor', 'authorizer_id' => null]]);

        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $aprobar->id]);
        $req = RoleRequest::first();

        $this->assertTrue($req->payload['supervisor_missing']);
        $this->assertSame($this->security->id, $req->approvals->first()->approver_id);
        $this->assertSame('seguridad', $req->approvals->first()->approver_role);
    }

    public function test_application_rule_prevents_auto_approval(): void
    {
        ['position' => $p, 'app' => $app, 'crear' => $role, 'user' => $u] = $this->scenario('media');
        $p->roleRules()->attach($role->id, ['rule_type' => 'permitido']); // sería auto-aprobable...

        // ...pero una regla por aplicación exige autorización del owner.
        $this->rule(['name' => 'App→Owner', 'trigger_type' => 'application', 'application_id' => $app->id], [['authorizer_type' => 'fixed', 'authorizer_id' => $this->owner->id]]);

        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $u->id, 'requested_role_id' => $role->id]);
        $req = RoleRequest::first();

        $this->assertSame(RequestStatus::EnAprobacion, $req->status);
        $this->assertSame($this->owner->id, $req->approvals->first()->approver_id);
    }
}
