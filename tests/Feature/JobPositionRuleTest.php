<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\JobPosition;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPositionRuleTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function fixtures(): array
    {
        $cargo = JobPosition::create(['name' => 'Analista']);
        $app = Application::create(['name' => 'ERP', 'criticality_level' => 'media']);
        $role = $app->roles()->create(['name' => 'Aprobar Pago']);

        return [$cargo, $role];
    }

    public function test_admin_can_view_rules_page(): void
    {
        $this->actingAsRole('Administrador');
        [$cargo] = $this->fixtures();
        $this->get("/cargos/{$cargo->id}/reglas")->assertOk();
    }

    public function test_admin_can_add_rule(): void
    {
        $this->actingAsRole('Administrador');
        [$cargo, $role] = $this->fixtures();

        $this->post("/cargos/{$cargo->id}/reglas", ['role_id' => $role->id, 'rule_type' => 'no_permitido'])
            ->assertRedirect();

        $this->assertDatabaseHas('job_position_role', [
            'job_position_id' => $cargo->id,
            'role_id' => $role->id,
            'rule_type' => 'no_permitido',
        ]);
    }

    public function test_adding_same_role_upserts_rule(): void
    {
        $this->actingAsRole('Administrador');
        [$cargo, $role] = $this->fixtures();

        $this->post("/cargos/{$cargo->id}/reglas", ['role_id' => $role->id, 'rule_type' => 'permitido']);
        $this->post("/cargos/{$cargo->id}/reglas", ['role_id' => $role->id, 'rule_type' => 'no_permitido']);

        $this->assertDatabaseCount('job_position_role', 1);
        $this->assertDatabaseHas('job_position_role', [
            'job_position_id' => $cargo->id,
            'role_id' => $role->id,
            'rule_type' => 'no_permitido',
        ]);
    }

    public function test_admin_can_delete_rule(): void
    {
        $this->actingAsRole('Administrador');
        [$cargo, $role] = $this->fixtures();
        $cargo->roleRules()->attach($role->id, ['rule_type' => 'permitido']);

        $this->delete("/cargos/{$cargo->id}/reglas/{$role->id}")->assertRedirect();
        $this->assertDatabaseCount('job_position_role', 0);
    }

    public function test_consultor_cannot_add_rule(): void
    {
        $this->actingAsRole('Consultor');
        [$cargo, $role] = $this->fixtures();

        $this->post("/cargos/{$cargo->id}/reglas", ['role_id' => $role->id, 'rule_type' => 'permitido'])
            ->assertForbidden();
    }
}
