<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\RequestApproval;
use App\Models\RequestSodFinding;
use App\Models\Role;
use App\Models\RoleRequest;
use App\Models\SodConflict;
use App\Models\User;
use App\Services\ConflictScanner;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConflictReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $auditor;

    private Application $erp;

    private Application $crm;

    private Role $crear;

    private Role $aprobar;

    private Role $vender;

    private Area $fin;

    private Area $ven;

    private JobPosition $cargo;

    private SodConflict $alto;

    private SodConflict $medio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrador');
        $this->auditor = User::factory()->create();
        $this->auditor->assignRole('Auditor');

        $this->fin = Area::create(['name' => 'Finanzas', 'code' => 'FIN']);
        $this->ven = Area::create(['name' => 'Ventas', 'code' => 'VEN']);
        $this->cargo = JobPosition::create(['name' => 'Analista']);
        $this->erp = Application::create(['name' => 'ERP', 'criticality_level' => 'alta']);
        $this->crm = Application::create(['name' => 'CRM', 'criticality_level' => 'media']);
        $this->crear = $this->erp->roles()->create(['name' => 'Crear Proveedor']);
        $this->aprobar = $this->erp->roles()->create(['name' => 'Aprobar Pago']);
        $this->vender = $this->crm->roles()->create(['name' => 'Vender']);

        $this->alto = $this->pair($this->crear, $this->aprobar, 'alto', 'Fraude de proveedores');
        $this->medio = $this->pair($this->aprobar, $this->vender, 'medio');
    }

    private function pair(Role $a, Role $b, string $risk, ?string $desc = null): SodConflict
    {
        return SodConflict::create([
            'role_a_id' => min($a->id, $b->id), 'role_b_id' => max($a->id, $b->id),
            'risk_level' => $risk, 'description' => $desc,
        ]);
    }

    private function user(string $igg, ?Area $area = null, ?string $name = null): ManagedUser
    {
        return ManagedUser::create([
            'external_id' => 'E'.$igg, 'igg' => $igg, 'name' => $name ?? "Usuario {$igg}",
            'area_id' => ($area ?? $this->fin)->id, 'job_position_id' => $this->cargo->id,
        ]);
    }

    private function give(ManagedUser $u, Role $r, array $extra = []): void
    {
        $u->roles()->attach($r->id, $extra + ['assigned_at' => now()->subDays(3), 'source' => 'import']);
    }

    private function scan(array $filters = []): array
    {
        $s = app(ConflictScanner::class);

        return $s->select($s->query($filters))->get()->all();
    }

    // ---------- escáner ----------

    public function test_detects_only_current_conflicting_pairs(): void
    {
        $both = $this->user('A1');
        $this->give($both, $this->crear);
        $this->give($both, $this->aprobar);

        $one = $this->user('A2');
        $this->give($one, $this->crear);

        $expired = $this->user('A3');
        $this->give($expired, $this->crear);
        $this->give($expired, $this->aprobar, ['expires_at' => now()->subDay()]);

        $deleted = $this->user('A4');
        $this->give($deleted, $this->crear);
        $this->give($deleted, $this->aprobar);
        $deleted->delete();

        $rows = $this->scan();

        $this->assertCount(1, $rows);
        $this->assertSame('A1', $rows[0]->igg);
        $this->assertSame('alto', $rows[0]->risk_level);
        $this->assertNull($rows[0]->exception_request_id);
    }

    public function test_inactive_matrix_and_deleted_roles_are_ignored(): void
    {
        $u = $this->user('B1');
        $this->give($u, $this->crear);
        $this->give($u, $this->aprobar);
        $this->give($u, $this->vender);

        $this->assertCount(2, $this->scan()); // alto (crear/aprobar) + medio (aprobar/vender)

        $this->medio->update(['is_active' => false]);
        $this->assertCount(1, $this->scan());

        $this->vender->delete();
        $this->alto->update(['is_active' => true]);
        $this->assertCount(1, $this->scan());

        $this->crear->delete();
        $this->assertCount(0, $this->scan());
    }

    public function test_three_roles_produce_one_row_per_matrix_pair(): void
    {
        $u = $this->user('C1');
        $this->give($u, $this->crear);
        $this->give($u, $this->aprobar);
        $this->give($u, $this->vender);

        $rows = $this->scan();

        $this->assertSame(['alto', 'medio'], array_column($rows, 'risk_level'), 'Ordenado por riesgo.');
    }

    public function test_formal_exception_requires_approved_request_recording_that_conflict(): void
    {
        $u = $this->user('D1');
        $this->give($u, $this->crear);

        $approved = $this->request($u, $this->aprobar, 'aprobado', [$this->crear]);
        $this->give($u, $this->aprobar, ['role_request_id' => $approved->id, 'source' => 'request']);

        $rows = $this->scan();
        $this->assertSame($approved->id, (int) $rows[0]->exception_request_id);

        // Otro usuario con el mismo par pero asignado por importación: sin excepción.
        $v = $this->user('D2');
        $this->give($v, $this->crear);
        $this->give($v, $this->aprobar);

        $w = $this->user('D3');
        $this->give($w, $this->crear);
        $pending = $this->request($w, $this->aprobar, 'en_aprobacion', [$this->crear]);
        $this->give($w, $this->aprobar, ['role_request_id' => $pending->id, 'source' => 'request']);

        $byIgg = collect($this->scan())->keyBy('igg');
        $this->assertNotNull($byIgg['D1']->exception_request_id);
        $this->assertNull($byIgg['D2']->exception_request_id);
        $this->assertNull($byIgg['D3']->exception_request_id, 'Una solicitud no aprobada no es excepción.');
    }

    private function request(ManagedUser $u, Role $role, string $status, array $conflicting, string $risk = 'alto'): RoleRequest
    {
        $req = RoleRequest::create([
            'managed_user_id' => $u->id, 'requested_role_id' => $role->id, 'type' => 'asignacion',
            'status' => $status, 'requester_id' => $this->admin->id, 'has_sod_conflict' => true,
            'justification' => 'Cubre vacaciones del titular',
        ]);
        foreach ($conflicting as $c) {
            RequestSodFinding::create(['role_request_id' => $req->id, 'conflicting_role_id' => $c->id, 'risk_level' => $risk]);
        }
        RequestApproval::create([
            'role_request_id' => $req->id, 'approver_id' => $this->admin->id, 'approver_role' => 'seguridad', 'step_order' => 1,
            'status' => $status === 'aprobado' ? 'aprobado' : 'pendiente',
            'resolved_at' => $status === 'aprobado' ? now() : null,
            'resolved_by' => $status === 'aprobado' ? $this->admin->id : null,
            'comments' => $status === 'aprobado' ? 'Riesgo asumido con control compensatorio' : null,
        ]);

        return $req;
    }

    public function test_filters_and_summary(): void
    {
        $a = $this->user('E1', $this->fin, 'Ana Fin');
        $this->give($a, $this->crear);
        $this->give($a, $this->aprobar);
        $this->give($a, $this->vender); // + medio

        $b = $this->user('E2', $this->ven, 'Beto Ventas');
        $this->give($b, $this->aprobar);
        $this->give($b, $this->vender); // medio

        $this->assertCount(3, $this->scan());
        $this->assertCount(1, $this->scan(['risk_level' => 'alto']));
        $this->assertCount(2, $this->scan(['risk_level' => 'medio']));
        $this->assertCount(1, $this->scan(['area_id' => $this->ven->id]));
        $this->assertCount(1, $this->scan(['search' => 'Beto']));
        $this->assertCount(1, $this->scan(['search' => 'E2']));
        $this->assertCount(3, $this->scan(['application_id' => $this->erp->id]));
        $this->assertCount(2, $this->scan(['application_id' => $this->crm->id]));
        $this->assertCount(3, $this->scan(['exception' => 'sin']));
        $this->assertCount(0, $this->scan(['exception' => 'con']));

        $this->assertSame(
            ['total' => 3, 'usuarios' => 2, 'alto' => 1, 'medio' => 2, 'bajo' => 0, 'con_excepcion' => 0, 'sin_excepcion' => 3],
            app(ConflictScanner::class)->summary(),
        );
    }

    // ---------- reporte de conflictos (HTTP) ----------

    public function test_conflict_report_permissions(): void
    {
        $solicitante = User::factory()->create();
        $solicitante->assignRole('Solicitante');
        $consultor = User::factory()->create();
        $consultor->assignRole('Consultor');

        $this->actingAs($solicitante)->get('/reportes/conflictos')->assertForbidden();
        $this->actingAs($this->auditor)->get('/reportes/conflictos')->assertOk();
        $this->actingAs($this->admin)->get('/reportes/conflictos')->assertOk();

        // Exportar exige reportes.exportar: el Consultor solo ve.
        $this->actingAs($consultor)->get('/reportes/conflictos')->assertOk();
        $this->actingAs($consultor)->get('/reportes/conflictos/export')->assertForbidden();
        $this->actingAs($this->auditor)->get('/reportes/conflictos/export')->assertOk();
    }

    public function test_conflict_report_page_props_and_csv(): void
    {
        $u = $this->user('F1', $this->fin, '=HYPERLINK("http://x")');
        $this->give($u, $this->crear);
        $this->give($u, $this->aprobar);

        $this->actingAs($this->auditor)->get('/reportes/conflictos?risk_level=alto')->assertInertia(fn ($p) => $p
            ->component('Reportes/Conflictos')
            ->where('resumen.total', 1)->where('resumen.alto', 1)
            ->where('filas.data.0.igg', 'F1')
            ->where('filas.data.0.risk_level', 'alto')
            ->where('filas.data.0.description', 'Fraude de proveedores')
            ->where('can.export', true));

        $csv = $this->actingAs($this->auditor)->get('/reportes/conflictos/export')->streamedContent();
        $this->assertStringContainsString('Fraude de proveedores', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Las fórmulas se neutralizan.');
        $this->assertStringContainsString('Sin excepción formal', $csv);
    }

    // ---------- reporte de excepciones ----------

    public function test_exception_report_lists_only_approved_conflict_requests(): void
    {
        $u = $this->user('G1');
        $ok = $this->request($u, $this->aprobar, 'aprobado', [$this->crear]);
        $this->request($u, $this->vender, 'en_aprobacion', [$this->aprobar]);
        $this->request($u, $this->crear, 'rechazado', [$this->aprobar]);
        $clean = $this->request($u, $this->vender, 'aprobado', []);
        $clean->update(['has_sod_conflict' => false]);

        $this->actingAs($this->auditor)->get('/reportes/excepciones')->assertInertia(fn ($p) => $p
            ->component('Reportes/Excepciones')
            ->has('filas.data', 1)
            ->where('filas.data.0.id', $ok->id)
            ->where('filas.data.0.max_risk', 'alto')
            ->where('filas.data.0.conflicts.0.role', 'Crear Proveedor')
            ->where('filas.data.0.justification', 'Cubre vacaciones del titular')
            ->where('filas.data.0.approvers.0.comments', 'Riesgo asumido con control compensatorio'));
    }

    public function test_exception_report_filters_permissions_and_csv(): void
    {
        $u = $this->user('H1', $this->fin, 'Hugo');
        $r1 = $this->request($u, $this->aprobar, 'aprobado', [$this->crear], 'alto');
        $r2 = $this->request($u, $this->vender, 'aprobado', [$this->aprobar], 'medio');

        $ids = fn (string $qs) => collect($this->actingAs($this->auditor)->get("/reportes/excepciones{$qs}")
            ->viewData('page')['props']['filas']['data'])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$r1->id, $r2->id], $ids(''));
        $this->assertSame([$r1->id], $ids('?risk_level=alto'));
        $this->assertSame([$r2->id], $ids('?application_id='.$this->crm->id));
        $this->assertSame([$r2->id], $ids('?search=%23'.$r2->id));
        $this->assertSame([], $ids('?date_from='.now()->addDay()->toDateString()));
        $this->assertEqualsCanonicalizing([$r1->id, $r2->id], $ids('?date_to='.now()->toDateString()));

        $solicitante = User::factory()->create();
        $solicitante->assignRole('Solicitante');
        $this->actingAs($solicitante)->get('/reportes/excepciones')->assertForbidden();
        $this->actingAs($solicitante)->get('/reportes/excepciones/export')->assertForbidden();

        $csv = $this->actingAs($this->auditor)->get('/reportes/excepciones/export?risk_level=alto')->streamedContent();
        $this->assertStringContainsString('#'.$r1->id, $csv);
        $this->assertStringNotContainsString('#'.$r2->id, $csv);
        $this->assertStringContainsString('Riesgo asumido con control compensatorio', $csv);
    }

    // ---------- integración ----------

    public function test_end_to_end_request_with_conflict_becomes_formal_exception(): void
    {
        $security = User::factory()->create();
        $security->assignRole('Gestor de Seguridad');
        $u = $this->user('I1');
        $this->give($u, $this->crear);
        $this->cargo->roleRules()->attach($this->aprobar->id, ['rule_type' => 'permitido']);

        $this->actingAs($this->admin)->post('/solicitudes', [
            'managed_user_id' => $u->id, 'requested_role_id' => $this->aprobar->id, 'justification' => 'Cobertura temporal',
        ])->assertRedirect();
        $req = RoleRequest::firstOrFail();

        // Antes de aprobarse no hay conflicto en la foto.
        $this->assertCount(0, $this->scan());

        // La app es de criticidad alta (owner -> seguridad) y el solicitante no puede auto-aprobarse: aprueba Seguridad.
        while ($req->fresh()->status->value === 'en_aprobacion') {
            $step = $req->approvals()->where('status', 'pendiente')->orderBy('step_order')->first();
            $this->actingAs(User::find($step->approver_id))->post("/solicitudes/{$req->id}/aprobar", ['comments' => 'ok']);
        }

        $rows = $this->scan();
        $this->assertCount(1, $rows);
        $this->assertSame($req->id, (int) $rows[0]->exception_request_id);

        $this->actingAs($this->auditor)->get('/reportes/excepciones')->assertInertia(fn ($p) => $p
            ->where('filas.data.0.id', $req->id)->where('filas.data.0.justification', 'Cobertura temporal'));
    }

    // ---------- dashboard y menú ----------

    public function test_dashboard_kpi_visible_only_with_report_permission(): void
    {
        $u = $this->user('J1');
        $this->give($u, $this->crear);
        $this->give($u, $this->aprobar);

        $this->actingAs($this->auditor)->get('/dashboard')->assertInertia(fn ($p) => $p->where('kpis.conflictos_activos', 1));

        $solicitante = User::factory()->create();
        $solicitante->assignRole('Solicitante');
        $this->actingAs($solicitante)->get('/dashboard')->assertInertia(fn ($p) => $p->where('kpis.conflictos_activos', null));
    }

    public function test_menu_items_are_seeded_and_migration_is_idempotent(): void
    {
        $this->seed(MenuSeeder::class);
        $routes = fn () => \DB::table('menus')->whereIn('route', ['reportes.conflictos', 'reportes.excepciones'])->pluck('route')->all();
        $this->assertCount(2, $routes());

        $migration = require database_path('migrations/2026_10_07_000004_seed_conflict_report_menu_items.php');
        $migration->up();
        $migration->up();
        $this->assertCount(2, $routes());

        // Base ya sembrada sin los ítems: la migración los inserta una sola vez.
        \DB::table('menus')->whereIn('route', ['reportes.conflictos', 'reportes.excepciones'])->delete();
        $migration->up();
        $migration->up();
        $this->assertCount(2, $routes());
    }
}
