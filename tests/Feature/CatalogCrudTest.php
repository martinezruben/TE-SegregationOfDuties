<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
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

    public function test_cargos_can_be_filtered(): void
    {
        $this->actingAsRole('Administrador');
        $gen = JobPosition::create(['name' => 'Analista (gen)', 'is_generic' => true, 'is_active' => true]);
        JobPosition::create(['name' => 'Analista Sr', 'is_generic' => false, 'is_active' => true, 'generic_position_id' => $gen->id]);
        JobPosition::create(['name' => 'Gerente', 'description' => 'Dirige el área', 'is_generic' => false, 'is_active' => false]);

        $count = fn (string $qs) => $this->get('/cargos'.$qs)->viewData('page')['props']['cargos']['total'];

        $this->assertSame(3, $count(''));
        $this->assertSame(2, $count('?search=analista'));
        $this->assertSame(1, $count('?search=dirige'));
        $this->assertSame(1, $count('?type=generico'));
        $this->assertSame(2, $count('?type=especifico'));
        $this->assertSame(1, $count('?generic_position_id='.$gen->id));
        $this->assertSame(1, $count('?status=inactivo'));
        $this->assertSame(0, $count('?search=analista&status=inactivo'));
    }

    public function test_managed_users_can_be_filtered(): void
    {
        $admin = $this->actingAsRole('Administrador');
        $ti = Area::create(['code' => 'TI', 'name' => 'TI']);
        $fin = Area::create(['code' => 'FIN', 'name' => 'Finanzas']);
        $cargo = JobPosition::create(['name' => 'Analista', 'is_active' => true]);
        $otro = JobPosition::create(['name' => 'Contador', 'is_active' => true]);

        ManagedUser::create(['external_id' => 'E1', 'igg' => 'ig1', 'name' => 'Ana', 'area_id' => $ti->id, 'job_position_id' => $cargo->id, 'supervisor_id' => $admin->id, 'is_active' => true]);
        ManagedUser::create(['external_id' => 'E2', 'igg' => 'ig2', 'name' => 'Beto', 'area_id' => $fin->id, 'job_position_id' => $otro->id, 'is_active' => false]);

        $count = fn (string $qs) => $this->get('/gestionados'.$qs)->viewData('page')['props']['usuarios']['total'];

        $this->assertSame(2, $count(''));
        $this->assertSame(1, $count('?search=ig2'));
        $this->assertSame(1, $count('?area_id='.$ti->id));
        $this->assertSame(1, $count('?job_position_id='.$cargo->id));
        $this->assertSame(1, $count('?supervisor='.$admin->id));
        $this->assertSame(1, $count('?supervisor=none'));
        $this->assertSame(1, $count('?status=inactivo'));
        // El texto y los demás filtros se combinan con AND (no se cuela el OR de la búsqueda).
        $this->assertSame(0, $count('?search=ana&area_id='.$fin->id));
    }

    public function test_areas_can_be_filtered(): void
    {
        $this->actingAsRole('Administrador');
        Area::create(['code' => 'TI', 'name' => 'Tecnología', 'is_active' => true]);
        Area::create(['code' => 'FIN', 'name' => 'Finanzas', 'is_active' => false]);

        $count = fn (string $qs) => $this->get('/areas'.$qs)->viewData('page')['props']['areas']['total'];

        $this->assertSame(2, $count(''));
        $this->assertSame(1, $count('?search=fin'));
        $this->assertSame(1, $count('?status=activo'));
        $this->assertSame(0, $count('?search=fin&status=activo'));
    }

    public function test_applications_can_be_filtered(): void
    {
        $admin = $this->actingAsRole('Administrador');
        Application::create(['name' => 'ERP', 'description' => 'Finanzas', 'criticality_level' => 'alta', 'owner_id' => $admin->id, 'is_active' => true]);
        Application::create(['name' => 'CRM', 'criticality_level' => 'baja', 'is_active' => false]);

        $count = fn (string $qs) => $this->get('/aplicaciones'.$qs)->viewData('page')['props']['aplicaciones']['total'];

        $this->assertSame(2, $count(''));
        $this->assertSame(1, $count('?search=finanzas'));
        $this->assertSame(1, $count('?criticality=alta'));
        $this->assertSame(1, $count('?owner='.$admin->id));
        $this->assertSame(1, $count('?owner=none'));
        $this->assertSame(1, $count('?status=inactivo'));
        $this->assertSame(0, $count('?criticality=alta&status=inactivo'));
    }

    public function test_agents_can_be_filtered(): void
    {
        $admin = $this->actingAsRole('Administrador');
        $other = User::factory()->create(['name' => 'Zoe Auditora', 'email' => 'zoe@example.com']);
        $other->assignRole('Auditor');
        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

        $count = fn (string $qs) => $this->get('/agentes'.$qs)->viewData('page')['props']['agentes']['total'];

        $this->assertSame(2, $count(''));
        $this->assertSame(1, $count('?search=zoe@'));
        $this->assertSame(1, $count('?role=Auditor'));
        $this->assertSame(1, $count('?two_factor=activo'));
        $this->assertSame(1, $count('?two_factor=inactivo'));
        $this->assertSame(2, $count('?role=NoExiste')); // rol inválido se ignora
        $this->assertSame(0, $count('?role=Auditor&two_factor=activo'));
    }
}
