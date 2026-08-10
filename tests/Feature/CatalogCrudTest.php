<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\JobPosition;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCrudTest extends TestCase
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

    public function test_admin_can_view_cargos_and_gestionados(): void
    {
        $this->actingAsRole('Administrador');
        $this->get('/cargos')->assertOk();
        $this->get('/gestionados')->assertOk();
    }

    public function test_admin_can_create_generic_and_specific_cargo(): void
    {
        $this->actingAsRole('Administrador');

        $this->post('/cargos', ['name' => 'Analista (gen)', 'is_generic' => true, 'is_active' => true])
            ->assertRedirect();
        $generic = JobPosition::where('name', 'Analista (gen)')->firstOrFail();
        $this->assertTrue($generic->is_generic);

        $this->post('/cargos', [
            'name' => 'Analista Fin',
            'is_generic' => false,
            'is_active' => true,
            'generic_position_id' => $generic->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('job_positions', [
            'name' => 'Analista Fin',
            'generic_position_id' => $generic->id,
        ]);
    }

    public function test_generic_reference_must_be_generic(): void
    {
        $this->actingAsRole('Administrador');
        $specific = JobPosition::create(['name' => 'No genérico', 'is_generic' => false]);

        $this->post('/cargos', [
            'name' => 'X',
            'is_generic' => false,
            'generic_position_id' => $specific->id,
        ])->assertSessionHasErrors('generic_position_id');
    }

    public function test_consultor_cannot_create_cargo(): void
    {
        $this->actingAsRole('Consultor');
        $this->post('/cargos', ['name' => 'Y'])->assertForbidden();
    }

    public function test_admin_can_create_managed_user(): void
    {
        $this->actingAsRole('Administrador');
        $area = Area::create(['name' => 'Finanzas', 'code' => 'FIN']);
        $cargo = JobPosition::create(['name' => 'Analista']);

        $this->post('/gestionados', [
            'external_id' => 'EMP-1',
            'igg' => 'IGG-1',
            'name' => 'Pedro',
            'area_id' => $area->id,
            'job_position_id' => $cargo->id,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('managed_users', ['igg' => 'IGG-1', 'name' => 'Pedro']);
    }

    public function test_managed_user_igg_must_be_unique(): void
    {
        $this->actingAsRole('Administrador');
        $area = Area::create(['name' => 'A', 'code' => 'A']);
        $cargo = JobPosition::create(['name' => 'C']);
        $this->post('/gestionados', [
            'external_id' => 'E1', 'igg' => 'DUP', 'name' => 'Uno',
            'area_id' => $area->id, 'job_position_id' => $cargo->id,
        ])->assertRedirect();

        $this->post('/gestionados', [
            'external_id' => 'E2', 'igg' => 'DUP', 'name' => 'Dos',
            'area_id' => $area->id, 'job_position_id' => $cargo->id,
        ])->assertSessionHasErrors('igg');
    }

    public function test_consultor_cannot_create_managed_user(): void
    {
        $this->actingAsRole('Consultor');
        $area = Area::create(['name' => 'A', 'code' => 'A']);
        $cargo = JobPosition::create(['name' => 'C']);

        $this->post('/gestionados', [
            'external_id' => 'E9', 'igg' => 'IGG9', 'name' => 'Z',
            'area_id' => $area->id, 'job_position_id' => $cargo->id,
        ])->assertForbidden();
    }
}
