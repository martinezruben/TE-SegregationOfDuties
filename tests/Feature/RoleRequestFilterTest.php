<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleRequestFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrador');
    }

    private function seedRequests(): array
    {
        $area = Area::create(['name' => 'Fin', 'code' => 'FIN']);
        $pos = JobPosition::create(['name' => 'Analista']);
        $appA = Application::create(['name' => 'App A', 'criticality_level' => 'media']);
        $appB = Application::create(['name' => 'App B', 'criticality_level' => 'media']);
        $roleA = $appA->roles()->create(['name' => 'Rol A']);
        $roleB = $appB->roles()->create(['name' => 'Rol B']);

        $maria = ManagedUser::create(['external_id' => 'E1', 'igg' => 'IGG1', 'name' => 'María López', 'area_id' => $area->id, 'job_position_id' => $pos->id]);
        $juan = ManagedUser::create(['external_id' => 'E2', 'igg' => 'IGG2', 'name' => 'Juan Pérez', 'area_id' => $area->id, 'job_position_id' => $pos->id]);

        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $maria->id, 'requested_role_id' => $roleA->id]);
        $this->actingAs($this->admin)->post('/solicitudes', ['managed_user_id' => $juan->id, 'requested_role_id' => $roleB->id]);

        return compact('appA', 'roleA', 'roleB', 'maria', 'juan');
    }

    public function test_filter_by_application(): void
    {
        ['appA' => $appA] = $this->seedRequests();

        $this->actingAs($this->admin)
            ->get('/solicitudes?application_id='.$appA->id)
            ->assertInertia(fn (Assert $p) => $p
                ->component('Solicitudes/Index')
                ->has('solicitudes.data', 1)
                ->where('solicitudes.data.0.application', 'App A'));
    }

    public function test_filter_by_role_and_search(): void
    {
        ['roleB' => $roleB] = $this->seedRequests();

        $this->actingAs($this->admin)->get('/solicitudes?role_id='.$roleB->id)
            ->assertInertia(fn (Assert $p) => $p->has('solicitudes.data', 1)->where('solicitudes.data.0.role', 'Rol B'));

        $this->actingAs($this->admin)->get('/solicitudes?search=María')
            ->assertInertia(fn (Assert $p) => $p->has('solicitudes.data', 1)->where('solicitudes.data.0.user', 'María López'));
    }

    public function test_filter_by_request_id(): void
    {
        $this->seedRequests();

        $this->actingAs($this->admin)->get('/solicitudes?id=1')
            ->assertInertia(fn (Assert $p) => $p->has('solicitudes.data', 1)->where('solicitudes.data.0.id', 1));
    }
}
