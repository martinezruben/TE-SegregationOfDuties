<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTest extends TestCase
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

    public function test_admin_can_view_applications(): void
    {
        $this->actingAsRole('Administrador');
        $this->get('/aplicaciones')->assertOk();
    }

    public function test_admin_can_create_application(): void
    {
        $owner = $this->actingAsRole('Administrador');

        $this->post('/aplicaciones', [
            'name' => 'ERP',
            'description' => 'Sistema',
            'criticality_level' => 'alta',
            'owner_id' => $owner->id,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('applications', ['name' => 'ERP', 'criticality_level' => 'alta']);
    }

    public function test_criticality_must_be_valid(): void
    {
        $this->actingAsRole('Administrador');

        $this->post('/aplicaciones', ['name' => 'X', 'criticality_level' => 'critica'])
            ->assertSessionHasErrors('criticality_level');
    }

    public function test_name_must_be_unique(): void
    {
        $this->actingAsRole('Administrador');
        $this->post('/aplicaciones', ['name' => 'Dup', 'criticality_level' => 'baja'])->assertRedirect();
        $this->post('/aplicaciones', ['name' => 'Dup', 'criticality_level' => 'baja'])->assertSessionHasErrors('name');
    }

    public function test_consultor_cannot_create_application(): void
    {
        $this->actingAsRole('Consultor');
        $this->post('/aplicaciones', ['name' => 'Y', 'criticality_level' => 'baja'])->assertForbidden();
    }
}
