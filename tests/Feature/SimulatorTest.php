<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SimulatorTest extends TestCase
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

    public function test_admin_can_view_simulator(): void
    {
        $this->actingAsRole('Administrador');
        $this->get('/simulador')->assertOk();
    }

    public function test_role_without_permission_is_forbidden(): void
    {
        // "Definidor de Matrices" no tiene simulador.ver.
        $this->actingAsRole('Definidor de Matrices');
        $this->get('/simulador')->assertForbidden();
    }

    public function test_simulation_returns_viable_when_allowed_and_no_conflict(): void
    {
        $this->actingAsRole('Administrador');

        $area = Area::create(['name' => 'Fin', 'code' => 'FIN']);
        $position = JobPosition::create(['name' => 'Analista']);
        $app = Application::create(['name' => 'ERP', 'criticality_level' => 'media']);
        $role = $app->roles()->create(['name' => 'Consultar']);
        $position->roleRules()->attach($role->id, ['rule_type' => 'permitido']);
        $user = ManagedUser::create([
            'external_id' => 'E1', 'igg' => 'I1', 'name' => 'Ana',
            'area_id' => $area->id, 'job_position_id' => $position->id,
        ]);

        $this->get("/simulador?managed_user_id={$user->id}&role_id={$role->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Simulador/Index')
                ->where('evaluation.viable', true)
                ->where('evaluation.has_conflict', false)
                ->where('evaluation.cargo.allowed', true));
    }
}
