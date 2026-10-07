<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\RequestApproval;
use App\Models\Role;
use App\Models\RoleRequest;
use App\Models\User;
use App\Services\DashboardCharts;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    private ManagedUser $maria;

    private Application $erp;

    private Application $crm;

    private Role $pagos;

    private Role $proveedores;

    private Role $vender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->auditor = User::factory()->create();
        $this->auditor->assignRole('Auditor');

        $this->maria = ManagedUser::create([
            'external_id' => 'E1', 'igg' => 'I1', 'name' => 'María',
            'area_id' => Area::create(['name' => 'Fin', 'code' => 'FIN'])->id,
            'job_position_id' => JobPosition::create(['name' => 'Analista'])->id,
        ]);
        $this->erp = Application::create(['name' => 'ERP', 'criticality_level' => 'alta']);
        $this->crm = Application::create(['name' => 'CRM', 'criticality_level' => 'media']);
        $this->pagos = $this->erp->roles()->create(['name' => 'Aprobar Pago']);
        $this->proveedores = $this->erp->roles()->create(['name' => 'Crear Proveedor']);
        $this->vender = $this->crm->roles()->create(['name' => 'Vender']);
    }

    private function request(Role $role, string $status = 'pendiente', bool $conflict = false, int $daysAgo = 0): RoleRequest
    {
        $r = RoleRequest::create([
            'managed_user_id' => $this->maria->id, 'requested_role_id' => $role->id, 'type' => 'asignacion',
            'status' => $status, 'requester_id' => $this->auditor->id, 'has_sod_conflict' => $conflict,
        ]);
        $r->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $r;
    }

    private function charts(string $qs = ''): array
    {
        return $this->actingAs($this->auditor)->get("/dashboard{$qs}")->viewData('page')['props']['charts'];
    }

    public function test_charts_are_only_for_users_who_can_see_reports(): void
    {
        $solicitante = User::factory()->create();
        $solicitante->assignRole('Solicitante');

        $this->actingAs($solicitante)->get('/dashboard')->assertInertia(fn ($p) => $p->where('charts', null));
        $this->actingAs($this->auditor)->get('/dashboard')->assertInertia(fn ($p) => $p->has('charts.by_status'));
    }

    public function test_period_is_validated_and_scopes_the_status_chart(): void
    {
        $this->request($this->pagos, 'aprobado', daysAgo: 10);
        $this->request($this->pagos, 'aprobado', daysAgo: 60);
        $this->request($this->pagos, 'rechazado', daysAgo: 200);

        $count = fn (array $c, string $label) => collect($c['by_status'])->firstWhere('label', $label)['value'];

        $default = $this->charts();
        $this->assertSame(90, $default['days']);
        $this->assertSame(2, $count($default, 'Aprobado'));
        $this->assertSame(0, $count($default, 'Rechazado'));

        $this->assertSame(1, $count($this->charts('?days=30'), 'Aprobado'));
        $this->assertSame(1, $count($this->charts('?days=365'), 'Rechazado'));

        // Valores no permitidos caen al período por defecto.
        foreach (['7', 'abc', '99999', '-1'] as $bad) {
            $this->assertSame(90, $this->charts("?days={$bad}")['days']);
        }
    }

    public function test_status_chart_lists_every_state_in_fixed_order(): void
    {
        $labels = array_column($this->charts()['by_status'], 'label');

        $this->assertSame(['Pendiente', 'En aprobación', 'Aprobado', 'Rechazado', 'Cancelado', 'Auto-aprobado'], $labels);
    }

    public function test_conflict_trend_counts_detected_and_accepted_per_month(): void
    {
        $this->request($this->pagos, 'aprobado', conflict: true, daysAgo: 0);
        $this->request($this->pagos, 'rechazado', conflict: true, daysAgo: 0);
        $this->request($this->vender, 'aprobado', conflict: false, daysAgo: 0); // sin conflicto: no cuenta
        $this->request($this->pagos, 'aprobado', conflict: true, daysAgo: 40);

        $trend = $this->charts('?days=90')['conflicts_trend'];

        $this->assertCount(3, $trend);
        $current = end($trend);
        $this->assertSame(['detectados' => 2, 'aceptados' => 1], ['detectados' => $current['detectados'], 'aceptados' => $current['aceptados']]);
        $this->assertSame(3, array_sum(array_column($trend, 'detectados')));
        $this->assertSame(2, array_sum(array_column($trend, 'aceptados')));

        // El período largo muestra más meses (máx. 12), con los meses vacíos incluidos.
        $this->assertCount(6, $this->charts('?days=180')['conflicts_trend']);
        $this->assertCount(12, $this->charts('?days=365')['conflicts_trend']);
    }

    public function test_aging_buckets_pending_steps_by_age_and_ignores_closed_requests(): void
    {
        $step = function (string $requestStatus, int $ageDays, string $stepStatus = 'pendiente') {
            $r = $this->request($this->pagos, $requestStatus);

            return RequestApproval::create([
                'role_request_id' => $r->id, 'approver_id' => $this->auditor->id, 'approver_role' => 'seguridad',
                'step_order' => 1, 'status' => $stepStatus, 'activated_at' => now()->subDays($ageDays),
            ]);
        };
        $step('en_aprobacion', 0);
        $step('en_aprobacion', 1);
        $step('pendiente', 3);
        $step('en_aprobacion', 5);
        $step('en_aprobacion', 30);
        $step('rechazado', 10);                  // solicitud cerrada: no cuenta
        $step('en_aprobacion', 2, 'aprobado');   // paso ya resuelto: no cuenta

        $aging = collect($this->charts()['aging'])->pluck('value', 'label')->all();

        $this->assertSame(['0–1 días' => 2, '2–3 días' => 1, '4–7 días' => 1, 'Más de 7 días' => 1], $aging);
    }

    public function test_top_apps_and_roles_are_ranked_and_limited_to_five(): void
    {
        foreach (range(1, 3) as $i) {
            $this->request($this->pagos);
        }
        $this->request($this->proveedores);
        $this->request($this->proveedores);
        $this->request($this->vender);

        $charts = $this->charts();

        $this->assertSame([['label' => 'ERP', 'value' => 5], ['label' => 'CRM', 'value' => 1]], $charts['top_apps']);
        $this->assertSame('Aprobar Pago', $charts['top_roles'][0]['label']);
        $this->assertSame('ERP', $charts['top_roles'][0]['detail']);
        $this->assertSame(3, $charts['top_roles'][0]['value']);
        $this->assertSame(['Aprobar Pago', 'Crear Proveedor', 'Vender'], array_column($charts['top_roles'], 'label'));

        // Más de 5 roles: solo los 5 primeros.
        foreach (range(1, 6) as $i) {
            $this->request($this->erp->roles()->create(['name' => "Extra {$i}"]));
        }
        $this->assertCount(5, $this->charts()['top_roles']);
    }

    public function test_period_helper(): void
    {
        $this->assertSame(30, DashboardCharts::period('30'));
        $this->assertSame(365, DashboardCharts::period(365));
        $this->assertSame(90, DashboardCharts::period(null));
        $this->assertSame(90, DashboardCharts::period('1; DROP TABLE'));
    }
}
