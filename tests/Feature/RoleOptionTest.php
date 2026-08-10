<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Option;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleOptionTest extends TestCase
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

    private function app(): Application
    {
        return Application::create(['name' => 'ERP', 'criticality_level' => 'alta']);
    }

    public function test_admin_can_view_roles_page(): void
    {
        $this->actingAsRole('Administrador');
        $app = $this->app();
        $this->get("/aplicaciones/{$app->id}/roles")->assertOk();
    }

    public function test_admin_can_create_role_and_option(): void
    {
        $this->actingAsRole('Administrador');
        $app = $this->app();

        $this->post("/aplicaciones/{$app->id}/roles", ['name' => 'Aprobar Pago'])->assertRedirect();
        $role = Role::where('name', 'Aprobar Pago')->firstOrFail();
        $this->assertSame($app->id, $role->application_id);

        $this->post("/roles/{$role->id}/opciones", ['name' => 'Autorizar', 'action_key' => 'pago.aprobar'])
            ->assertRedirect();
        $this->assertDatabaseHas('options', ['role_id' => $role->id, 'action_key' => 'pago.aprobar']);
    }

    public function test_role_name_unique_per_application(): void
    {
        $this->actingAsRole('Administrador');
        $app = $this->app();
        $this->post("/aplicaciones/{$app->id}/roles", ['name' => 'Dup'])->assertRedirect();
        $this->post("/aplicaciones/{$app->id}/roles", ['name' => 'Dup'])->assertSessionHasErrors('name');
    }

    public function test_action_key_unique_per_role(): void
    {
        $this->actingAsRole('Administrador');
        $app = $this->app();
        $role = $app->roles()->create(['name' => 'R']);
        $this->post("/roles/{$role->id}/opciones", ['name' => 'A', 'action_key' => 'k'])->assertRedirect();
        $this->post("/roles/{$role->id}/opciones", ['name' => 'B', 'action_key' => 'k'])->assertSessionHasErrors('action_key');
    }

    public function test_deleting_role_deletes_options(): void
    {
        $this->actingAsRole('Administrador');
        $app = $this->app();
        $role = $app->roles()->create(['name' => 'R']);
        $option = $role->options()->create(['name' => 'A', 'action_key' => 'k']);

        $this->delete("/roles/{$role->id}")->assertRedirect();
        $this->assertDatabaseMissing('options', ['id' => $option->id]);
    }

    public function test_consultor_cannot_create_role(): void
    {
        $this->actingAsRole('Consultor');
        $app = $this->app();
        $this->post("/aplicaciones/{$app->id}/roles", ['name' => 'X'])->assertForbidden();
    }
}
