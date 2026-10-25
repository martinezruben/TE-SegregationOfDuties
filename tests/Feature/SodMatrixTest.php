<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Role;
use App\Models\SodConflict;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SodMatrixTest extends TestCase
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

    private function roles(): array
    {
        $app = Application::create(['name' => 'ERP', 'criticality_level' => 'alta']);

        return [
            $app->roles()->create(['name' => 'A']),
            $app->roles()->create(['name' => 'B']),
        ];
    }

    public function test_admin_can_view_matrix(): void
    {
        $this->actingAsRole('Administrador');
        $this->get('/matriz')->assertOk();
    }

    public function test_admin_can_create_conflict_and_pair_is_normalized(): void
    {
        $this->actingAsRole('Administrador');
        [$a, $b] = $this->roles();

        // Se envía en orden invertido; debe almacenarse con role_a_id < role_b_id.
        $this->post('/matriz', [
            'role_a_id' => $b->id,
            'role_b_id' => $a->id,
            'risk_level' => 'alto',
        ])->assertRedirect();

        $this->assertDatabaseHas('sod_matrix', [
            'role_a_id' => min($a->id, $b->id),
            'role_b_id' => max($a->id, $b->id),
        ]);
    }

    public function test_self_conflict_is_rejected(): void
    {
        $this->actingAsRole('Administrador');
        [$a] = $this->roles();

        $this->post('/matriz', ['role_a_id' => $a->id, 'role_b_id' => $a->id, 'risk_level' => 'alto'])
            ->assertSessionHasErrors('role_a_id');
    }

    public function test_duplicate_pair_is_rejected(): void
    {
        $this->actingAsRole('Administrador');
        [$a, $b] = $this->roles();
        SodConflict::create(['role_a_id' => min($a->id, $b->id), 'role_b_id' => max($a->id, $b->id), 'risk_level' => 'alto']);

        $this->post('/matriz', ['role_a_id' => $a->id, 'role_b_id' => $b->id, 'risk_level' => 'medio'])
            ->assertSessionHasErrors('role_b_id');
    }

    public function test_consultor_cannot_create_conflict(): void
    {
        $this->actingAsRole('Consultor');
        [$a, $b] = $this->roles();

        $this->post('/matriz', ['role_a_id' => $a->id, 'role_b_id' => $b->id, 'risk_level' => 'alto'])
            ->assertForbidden();
    }

    public function test_matrix_filters_by_search_application_risk_and_status(): void
    {
        $this->actingAsRole('Administrador');
        [$a, $b] = $this->roles();
        $crm = Application::create(['name' => 'CRM', 'criticality_level' => 'media']);
        $c = $crm->roles()->create(['name' => 'Vendedor']);

        SodConflict::create(['role_a_id' => $a->id, 'role_b_id' => $b->id, 'risk_level' => 'alto', 'description' => 'Fraude']);
        SodConflict::create(['role_a_id' => $b->id, 'role_b_id' => $c->id, 'risk_level' => 'bajo', 'is_active' => false]);

        $count = fn (string $qs) => $this->get('/matriz'.$qs)->viewData('page')['props']['conflictos']['total'];

        $this->assertSame(2, $count(''));
        $this->assertSame(1, $count('?search=Vendedor'));
        $this->assertSame(1, $count('?search=fraude'));
        $this->assertSame(2, $count('?application_id='.$a->application_id)); // ERP está en ambos pares
        $this->assertSame(1, $count('?application_id='.$crm->id));
        $this->assertSame(1, $count('?risk_level=bajo'));
        $this->assertSame(1, $count('?status=inactivo'));
        $this->assertSame(0, $count('?risk_level=alto&status=inactivo'));
    }
}
