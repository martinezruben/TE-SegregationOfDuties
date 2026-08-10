<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminTest extends TestCase
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

    // --- Agentes ---

    public function test_admin_can_view_agents(): void
    {
        $this->actingAsRole('Administrador');
        $this->get('/agentes')->assertOk();
    }

    public function test_admin_can_create_agent_with_roles(): void
    {
        $this->actingAsRole('Administrador');

        $this->post('/agentes', [
            'name' => 'Nuevo', 'email' => 'nuevo@sod.test', 'password' => 'password123',
            'roles' => ['Auditor'],
        ])->assertRedirect();

        $user = User::where('email', 'nuevo@sod.test')->firstOrFail();
        $this->assertTrue($user->hasRole('Auditor'));
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->actingAsRole('Administrador');
        $this->delete("/agentes/{$admin->id}")->assertForbidden();
    }

    public function test_consultor_cannot_create_agent(): void
    {
        $this->actingAsRole('Consultor');
        $this->post('/agentes', ['name' => 'X', 'email' => 'x@x.test', 'password' => 'password123'])
            ->assertForbidden();
    }

    // --- Roles y permisos ---

    public function test_admin_can_view_roles_permissions(): void
    {
        $this->actingAsRole('Administrador');
        $this->get('/roles-permisos')->assertOk();
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $this->actingAsRole('Administrador');
        $role = Role::findByName('Consultor');

        $this->put("/roles-permisos/{$role->id}", ['permissions' => ['areas.ver', 'cargos.ver']])
            ->assertRedirect();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('areas.ver'));
        $this->assertEqualsCanonicalizing(['areas.ver', 'cargos.ver'], $role->permissions->pluck('name')->all());
    }

    public function test_administrador_role_is_not_editable(): void
    {
        $this->actingAsRole('Administrador');
        $role = Role::findByName('Administrador');

        $this->put("/roles-permisos/{$role->id}", ['permissions' => []])->assertForbidden();
    }

    public function test_consultor_cannot_update_permissions(): void
    {
        $this->actingAsRole('Consultor');
        $role = Role::findByName('Auditor');
        $this->put("/roles-permisos/{$role->id}", ['permissions' => []])->assertForbidden();
    }

    // --- Bitácora ---

    public function test_auditor_can_view_bitacora(): void
    {
        $this->actingAsRole('Auditor');
        $this->get('/bitacora')->assertOk();
    }

    public function test_solicitante_cannot_view_bitacora(): void
    {
        $this->actingAsRole('Solicitante');
        $this->get('/bitacora')->assertForbidden();
    }
}
