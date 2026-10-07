<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Area;
use App\Models\CertificationCampaign;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\Role;
use App\Models\SodConflict;
use App\Models\User;
use App\Notifications\CertificationAssigned;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CertificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $security;

    private User $owner;

    private User $jefe;

    private User $auditor;

    private Application $erp;

    private Application $crm;

    private Role $crear;

    private Role $aprobar;

    private Role $vender;

    private Area $fin;

    private Area $ven;

    private ManagedUser $maria;

    private ManagedUser $luis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $mk = function (string $role) {
            $u = User::factory()->create();
            $u->assignRole($role);

            return $u;
        };
        $this->admin = $mk('Administrador');
        $this->security = $mk('Gestor de Seguridad');
        $this->owner = $mk('Owner de Aplicación');
        $this->jefe = $mk('Jefe');
        $this->auditor = $mk('Auditor');

        $this->fin = Area::create(['name' => 'Finanzas', 'code' => 'FIN']);
        $this->ven = Area::create(['name' => 'Ventas', 'code' => 'VEN']);
        $cargo = JobPosition::create(['name' => 'Analista']);
        $this->erp = Application::create(['name' => 'ERP', 'criticality_level' => 'alta', 'owner_id' => $this->owner->id]);
        $this->crm = Application::create(['name' => 'CRM', 'criticality_level' => 'media']); // sin owner
        $this->crear = $this->erp->roles()->create(['name' => 'Crear Proveedor']);
        $this->aprobar = $this->erp->roles()->create(['name' => 'Aprobar Pago']);
        $this->vender = $this->crm->roles()->create(['name' => 'Vender']);

        $this->maria = ManagedUser::create([
            'external_id' => 'E1', 'igg' => 'IGG1', 'name' => 'María', 'area_id' => $this->fin->id,
            'job_position_id' => $cargo->id, 'supervisor_id' => $this->jefe->id,
        ]);
        $this->luis = ManagedUser::create([
            'external_id' => 'E2', 'igg' => 'IGG2', 'name' => 'Luis', 'area_id' => $this->ven->id,
            'job_position_id' => $cargo->id,
        ]);
    }

    private function give(ManagedUser $u, Role $r, array $extra = []): void
    {
        $u->roles()->attach($r->id, $extra + ['assigned_at' => now()->subDays(10), 'source' => 'import']);
    }

    /** María: Crear+Aprobar (conflicto alto) en ERP y Vender en CRM; Luis: Vender en CRM. */
    private function foto(): void
    {
        SodConflict::create([
            'role_a_id' => min($this->crear->id, $this->aprobar->id), 'role_b_id' => max($this->crear->id, $this->aprobar->id),
            'risk_level' => 'alto',
        ]);
        $this->give($this->maria, $this->crear);
        $this->give($this->maria, $this->aprobar);
        $this->give($this->maria, $this->vender);
        $this->give($this->luis, $this->vender);
    }

    private function campaign(array $over = [], ?User $as = null): CertificationCampaign
    {
        $this->actingAs($as ?? $this->admin)->post('/certificaciones', $over + [
            'name' => 'Recertificación Q4', 'due_date' => now()->addDays(30)->toDateString(),
        ])->assertRedirect();

        return CertificationCampaign::latest('id')->firstOrFail();
    }

    private function decide(CertificationCampaign $c, User $as, array $ids, string $decision, ?string $comments = null)
    {
        return $this->actingAs($as)->post("/certificaciones/{$c->id}/decidir", [
            'item_ids' => $ids, 'decision' => $decision, 'comments' => $comments,
        ]);
    }

    // ---------- generación ----------

    public function test_campaign_snapshots_current_access_and_assigns_reviewers(): void
    {
        Notification::fake();
        $this->foto();
        $this->give($this->luis, $this->crear, ['expires_at' => now()->subDay()]); // expirado: no entra
        $deleted = ManagedUser::create(['external_id' => 'E3', 'igg' => 'IGG3', 'name' => 'Borrado', 'area_id' => $this->fin->id, 'job_position_id' => $this->maria->job_position_id]);
        $this->give($deleted, $this->vender);
        $deleted->delete();

        $c = $this->campaign();

        $this->assertSame(4, $c->items()->count());
        $by = fn (ManagedUser $u, Role $r) => $c->items()->where('managed_user_id', $u->id)->where('role_id', $r->id)->firstOrFail();

        // Revisor: owner de la app → supervisor del usuario → Seguridad.
        $this->assertSame($this->owner->id, $by($this->maria, $this->crear)->reviewer_id);
        $this->assertSame($this->jefe->id, $by($this->maria, $this->vender)->reviewer_id);
        $this->assertSame($this->security->id, $by($this->luis, $this->vender)->reviewer_id);

        // Snapshot para evidencia y riesgo SoD del acceso.
        $item = $by($this->maria, $this->crear);
        $this->assertSame('María', $item->user_name);
        $this->assertSame('ERP', $item->app_name);
        $this->assertSame('Crear Proveedor', $item->role_name);
        $this->assertSame('Finanzas', $item->area);
        $this->assertSame('alto', $item->conflict_risk);
        $this->assertNull($by($this->luis, $this->vender)->conflict_risk);
        $this->assertSame('pendiente', $item->decision);

        // Cada revisor recibe un aviso con su cantidad.
        Notification::assertSentTo($this->owner, CertificationAssigned::class, fn ($n) => $n->pending === 2 && ! $n->reminder);
        Notification::assertSentTo($this->jefe, CertificationAssigned::class, fn ($n) => $n->pending === 1);
        Notification::assertSentTo($this->security, CertificationAssigned::class, fn ($n) => $n->pending === 1);
        Notification::assertNotSentTo($this->auditor, CertificationAssigned::class);
    }

    public function test_without_security_manager_creator_is_the_fallback_reviewer(): void
    {
        $this->security->removeRole('Gestor de Seguridad');
        $this->foto();

        $c = $this->campaign();

        $this->assertSame($this->admin->id, $c->items()->where('managed_user_id', $this->luis->id)->value('reviewer_id'));
    }

    public function test_filters_limit_scope(): void
    {
        $this->foto();

        $this->assertSame(2, $this->campaign(['application_id' => $this->erp->id, 'name' => 'ERP'])->items()->count());
        $this->assertSame(1, $this->campaign(['area_id' => $this->ven->id, 'name' => 'Ventas'])->items()->count());

        $c = $this->campaign(['only_conflicts' => true, 'name' => 'Conflictos']);
        $this->assertSame(['Aprobar Pago', 'Crear Proveedor'], $c->items()->orderBy('role_name')->pluck('role_name')->all());
    }

    public function test_validation_and_permissions_on_create(): void
    {
        $this->foto();

        $this->actingAs($this->admin)->post('/certificaciones', ['name' => 'X', 'due_date' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors('due_date');
        $this->actingAs($this->admin)->post('/certificaciones', ['due_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('name');
        // Sin accesos que coincidan con el filtro.
        $empty = Application::create(['name' => 'Vacía', 'criticality_level' => 'baja']);
        $this->actingAs($this->admin)->post('/certificaciones', ['name' => 'X', 'due_date' => now()->addDay()->toDateString(), 'application_id' => $empty->id])
            ->assertSessionHasErrors('name');
        $this->assertSame(0, CertificationCampaign::count());

        foreach ([$this->owner, $this->jefe, $this->auditor] as $u) {
            $this->actingAs($u)->post('/certificaciones', ['name' => 'X', 'due_date' => now()->addDay()->toDateString()])->assertForbidden();
        }
    }

    // ---------- visibilidad ----------

    public function test_visibility_is_scoped_to_reviewers_unless_manager_or_auditor(): void
    {
        $this->foto();
        $c = $this->campaign();

        $count = fn (User $u) => count($this->actingAs($u)->get('/certificaciones')->viewData('page')['props']['campanas']);

        $this->assertSame(1, $count($this->admin));
        $this->assertSame(1, $count($this->auditor));
        $this->assertSame(1, $count($this->owner));
        $this->assertSame(1, $count($this->jefe));

        // El owner solo ve sus 2 accesos en el detalle; el auditor ve los 4.
        $this->actingAs($this->owner)->get("/certificaciones/{$c->id}")->assertInertia(fn ($p) => $p
            ->component('Certificaciones/Show')->where('items.total', 2)->where('can.seeAll', false)->where('resumen.total', 2));
        $this->actingAs($this->auditor)->get("/certificaciones/{$c->id}")->assertInertia(fn ($p) => $p
            ->where('items.total', 4)->where('can.seeAll', true)->has('revisores', 3));

        // Quien no es revisor de nada en la campaña no la ve.
        $otro = User::factory()->create();
        $otro->assignRole('Jefe');
        $this->actingAs($otro)->get("/certificaciones/{$c->id}")->assertForbidden();
        $this->assertSame(0, $count($otro));

        $solicitante = User::factory()->create();
        $solicitante->assignRole('Solicitante');
        $this->actingAs($solicitante)->get('/certificaciones')->assertForbidden();
    }

    // ---------- decisiones ----------

    public function test_reviewer_keeps_own_items_only(): void
    {
        $this->foto();
        $c = $this->campaign();
        $own = $c->items()->where('reviewer_id', $this->owner->id)->pluck('id')->all();
        $foreign = $c->items()->where('reviewer_id', $this->jefe->id)->pluck('id')->first();

        $this->decide($c, $this->owner, [...$own, $foreign], 'mantener', 'Sigue necesario')
            ->assertRedirect()->assertSessionHas('success');

        foreach ($own as $id) {
            $item = $c->items()->find($id);
            $this->assertSame('mantener', $item->decision);
            $this->assertSame($this->owner->id, $item->decided_by);
            $this->assertNotNull($item->decided_at);
            $this->assertSame('Sigue necesario', $item->comments);
        }
        $this->assertSame('pendiente', $c->items()->find($foreign)->decision, 'No puede decidir ítems ajenos.');
        $this->assertSame(3, \DB::table('user_role')->where('managed_user_id', $this->maria->id)->count(), 'Mantener no toca la foto.');

        // Una decisión ya tomada no se puede cambiar.
        $this->decide($c, $this->owner, $own, 'revocar', 'Me arrepentí')->assertSessionHas('error');
        $this->assertSame('mantener', $c->items()->find($own[0])->decision);
    }

    public function test_revoke_requires_comment_and_removes_access_from_foto(): void
    {
        $this->foto();
        $c = $this->campaign();
        $crearItem = $c->items()->where('managed_user_id', $this->maria->id)->where('role_id', $this->crear->id)->first();

        $this->decide($c, $this->owner, [$crearItem->id], 'revocar')->assertSessionHasErrors('comments');
        $this->assertSame('pendiente', $crearItem->fresh()->decision);
        $this->assertTrue($this->maria->roles()->where('roles.id', $this->crear->id)->exists());

        $this->decide($c, $this->owner, [$crearItem->id], 'revocar', 'Cambió de función')->assertSessionHas('success');

        $this->assertSame('revocar', $crearItem->fresh()->decision);
        $this->assertFalse($this->maria->roles()->where('roles.id', $this->crear->id)->exists(), 'El acceso sale de la foto.');
        $this->assertTrue($this->maria->roles()->where('roles.id', $this->aprobar->id)->exists(), 'Los demás accesos siguen.');
        $this->assertTrue($this->luis->roles()->where('roles.id', $this->vender->id)->exists());

        // El conflicto SoD desaparece del reporte de conflictos activos.
        $this->actingAs($this->auditor)->get('/reportes/conflictos')->assertInertia(fn ($p) => $p->where('resumen.total', 0));
    }

    public function test_only_users_with_review_permission_can_decide(): void
    {
        $this->foto();
        $c = $this->campaign();
        $item = $c->items()->first();

        // El auditor ve pero no decide.
        $this->decide($c, $this->auditor, [$item->id], 'mantener')->assertForbidden();
        // Aunque sea el revisor asignado, sin el permiso `revisar` no puede.
        $item->update(['reviewer_id' => $this->auditor->id]);
        $this->decide($c, $this->auditor, [$item->id], 'mantener')->assertForbidden();
        $this->decide($c, $this->owner, [], 'mantener')->assertSessionHasErrors('item_ids');
        $this->decide($c, $this->owner, [$item->id], 'otra')->assertSessionHasErrors('decision');
    }

    public function test_campaign_closes_automatically_when_everything_is_decided(): void
    {
        $this->foto();
        $c = $this->campaign();

        foreach ([$this->owner, $this->jefe, $this->security] as $reviewer) {
            $this->assertTrue($c->fresh()->isOpen());
            $this->decide($c, $reviewer, $c->items()->where('reviewer_id', $reviewer->id)->pluck('id')->all(), 'mantener');
        }

        $c->refresh();
        $this->assertSame('cerrada', $c->status);
        $this->assertNotNull($c->closed_at);
        $this->assertNull($c->closed_by);
    }

    public function test_manual_close_blocks_further_decisions(): void
    {
        $this->foto();
        $c = $this->campaign();
        $id = $c->items()->where('reviewer_id', $this->owner->id)->value('id');

        $this->actingAs($this->owner)->post("/certificaciones/{$c->id}/cerrar")->assertForbidden();
        $this->actingAs($this->auditor)->post("/certificaciones/{$c->id}/cerrar")->assertForbidden();

        $this->actingAs($this->admin)->post("/certificaciones/{$c->id}/cerrar")->assertRedirect();
        $this->assertSame('cerrada', $c->fresh()->status);
        $this->assertSame($this->admin->id, $c->fresh()->closed_by);

        $this->decide($c, $this->owner, [$id], 'mantener')->assertSessionHasErrors('decision');
        $this->assertSame('pendiente', \DB::table('certification_items')->where('id', $id)->value('decision'), 'Quedan registrados como sin respuesta.');
    }

    // ---------- exportación ----------

    public function test_export_is_for_managers_and_auditors_and_neutralizes_formulas(): void
    {
        $this->foto();
        $c = $this->campaign();
        $id = $c->items()->where('reviewer_id', $this->owner->id)->value('id');
        $this->decide($c, $this->owner, [$id], 'revocar', '=HYPERLINK("http://x")');

        $this->actingAs($this->owner)->get("/certificaciones/{$c->id}/export")->assertForbidden();

        $csv = $this->actingAs($this->auditor)->get("/certificaciones/{$c->id}/export")->streamedContent();
        $this->assertStringContainsString('María', $csv);
        $this->assertStringContainsString('revocar', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertSame(1 + 4, substr_count(trim($csv), "\n") + 1, 'Encabezado + 4 accesos.');
    }

    // ---------- recordatorios ----------

    public function test_reminders_for_due_soon_or_overdue_campaigns_once_per_day(): void
    {
        $this->foto();
        $far = $this->campaign(['name' => 'Lejana', 'due_date' => now()->addDays(30)->toDateString()]);
        $soon = $this->campaign(['name' => 'Pronto', 'due_date' => now()->addDays(2)->toDateString()]);
        $closed = $this->campaign(['name' => 'Cerrada', 'due_date' => now()->addDays(1)->toDateString()]);
        $closed->update(['status' => 'cerrada']);
        Notification::fake();

        $this->artisan('certifications:remind')->expectsOutput('Revisores recordados: 3'); // owner, jefe, security (solo "Pronto")
        Notification::assertSentTo($this->owner, CertificationAssigned::class, fn ($n) => $n->reminder && $n->campaign->is($soon));
        Notification::assertNotSentTo($this->owner, CertificationAssigned::class, fn ($n) => $n->campaign->is($far) || $n->campaign->is($closed));

        // Mismo día: no repite.
        $this->artisan('certifications:remind')->expectsOutput('Revisores recordados: 0');

        // Al día siguiente: repite.
        $this->travel(25)->hours();
        $this->artisan('certifications:remind')->expectsOutput('Revisores recordados: 3');

        // Ya sin pendientes para el owner: no se le recuerda.
        $this->decide($soon, $this->owner, $soon->items()->where('reviewer_id', $this->owner->id)->pluck('id')->all(), 'mantener');
        $this->travel(25)->hours();
        $this->artisan('certifications:remind')->expectsOutput('Revisores recordados: 2');
    }

    // ---------- menú ----------

    public function test_menu_item_is_for_reviewers_and_managers_only(): void
    {
        $this->seed(MenuSeeder::class);
        $has = fn (User $u) => collect($this->actingAs($u)->get('/dashboard')->viewData('page')['props']['menu'])
            ->pluck('children')->flatten(1)->pluck('route')->contains('certificaciones.index');

        $this->assertTrue($has($this->admin));
        $this->assertTrue($has($this->owner));
        $this->assertTrue($has($this->auditor));

        $solicitante = User::factory()->create();
        $solicitante->assignRole('Solicitante');
        $consultor = User::factory()->create();
        $consultor->assignRole('Consultor');
        $this->assertFalse($has($solicitante));
        $this->assertFalse($has($consultor));
    }
}
