<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrador');

        return $user;
    }

    private function consultor(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Consultor');

        return $user;
    }

    public function test_guest_is_redirected_from_areas(): void
    {
        $this->get('/areas')->assertRedirect('/login');
    }

    public function test_admin_can_view_areas_index(): void
    {
        $this->actingAs($this->admin())
            ->get('/areas')
            ->assertOk();
    }

    public function test_admin_can_create_area(): void
    {
        $this->actingAs($this->admin())
            ->post('/areas', ['name' => 'Finanzas', 'code' => 'FIN', 'is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('areas', ['code' => 'FIN']);
    }

    public function test_area_code_must_be_unique(): void
    {
        $admin = $this->admin();
        Area::create(['name' => 'Existente', 'code' => 'DUP']);

        $this->actingAs($admin)
            ->post('/areas', ['name' => 'Otra', 'code' => 'DUP'])
            ->assertSessionHasErrors('code');
    }

    public function test_consultor_cannot_create_area(): void
    {
        $this->actingAs($this->consultor())
            ->post('/areas', ['name' => 'X', 'code' => 'XXX'])
            ->assertForbidden();

        $this->assertDatabaseMissing('areas', ['code' => 'XXX']);
    }

    public function test_admin_can_delete_area(): void
    {
        $admin = $this->admin();
        $area = Area::create(['name' => 'Temp', 'code' => 'TMP']);

        $this->actingAs($admin)
            ->delete("/areas/{$area->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('areas', ['id' => $area->id]);
    }
}
