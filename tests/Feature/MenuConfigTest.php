<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuConfigTest extends TestCase
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

    public function test_admin_can_view_menus(): void
    {
        $this->actingAsRole('Administrador');
        $this->get('/menus')->assertOk();
    }

    public function test_admin_can_create_menu(): void
    {
        $this->actingAsRole('Administrador');
        $this->post('/menus', ['label' => 'Nuevo', 'permission_name' => 'areas.ver', 'order' => 5])
            ->assertRedirect();
        $this->assertDatabaseHas('menus', ['label' => 'Nuevo', 'permission_name' => 'areas.ver']);
    }

    public function test_menu_cannot_be_its_own_parent(): void
    {
        $this->actingAsRole('Administrador');
        $menu = Menu::create(['label' => 'X', 'order' => 0]);
        $this->put("/menus/{$menu->id}", ['label' => 'X', 'parent_id' => $menu->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_consultor_cannot_create_menu(): void
    {
        $this->actingAsRole('Consultor');
        $this->post('/menus', ['label' => 'X'])->assertForbidden();
    }
}
