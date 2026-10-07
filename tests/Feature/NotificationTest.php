<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\RoleRequest;
use App\Models\SodConflict;
use App\Models\User;
use App\Notifications\ApprovalPending;
use App\Notifications\HighRiskSodAlert;
use App\Notifications\RequestResolved;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $security;

    private array $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrador');
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner de Aplicación');
        $this->security = User::factory()->create();
        $this->security->assignRole('Gestor de Seguridad');
    }

    /** Escenario con conflicto SoD (riesgo dado) sobre una app de criticidad alta: owner -> seguridad. */
    private function scenario(string $risk = 'alto', string $criticality = 'alta', bool $ownerless = false): void
    {
        $area = Area::create(['name' => 'Fin', 'code' => 'FIN']);
        $position = JobPosition::create(['name' => 'Analista']);
        $app = Application::create(['name' => 'ERP', 'criticality_level' => $criticality, 'owner_id' => $ownerless ? null : $this->owner->id]);
        $crear = $app->roles()->create(['name' => 'Crear Proveedor']);
        $aprobar = $app->roles()->create(['name' => 'Aprobar Pago']);
        $user = ManagedUser::create([
            'external_id' => 'E1', 'igg' => 'I1', 'name' => 'María',
            'area_id' => $area->id, 'job_position_id' => $position->id,
        ]);
        $user->roles()->attach($crear->id, ['assigned_at' => now(), 'source' => 'import']);
        $position->roleRules()->attach($aprobar->id, ['rule_type' => 'permitido']);
        SodConflict::create(['role_a_id' => min($crear->id, $aprobar->id), 'role_b_id' => max($crear->id, $aprobar->id), 'risk_level' => $risk]);

        $this->s = compact('user', 'aprobar');
    }

    private function createRequest(): RoleRequest
    {
        $this->actingAs($this->admin)->post('/solicitudes', [
            'managed_user_id' => $this->s['user']->id,
            'requested_role_id' => $this->s['aprobar']->id,
        ])->assertRedirect();

        return RoleRequest::firstOrFail();
    }

    public function test_first_approver_is_notified_and_sla_starts(): void
    {
        Notification::fake();
        $this->scenario();

        $req = $this->createRequest();

        Notification::assertSentTo($this->owner, ApprovalPending::class, fn ($n) => ! $n->reminder);
        Notification::assertNotSentTo($this->security, ApprovalPending::class);
        $this->assertNotNull($req->approvals()->orderBy('step_order')->first()->activated_at);
        $this->assertNull($req->approvals()->orderBy('step_order')->skip(1)->first()->activated_at);
    }

    public function test_next_step_and_final_result_are_notified(): void
    {
        $this->scenario();
        $req = $this->createRequest();
        Notification::fake();

        $this->actingAs($this->owner)->post("/solicitudes/{$req->id}/aprobar");
        Notification::assertSentTo($this->security, ApprovalPending::class);
        Notification::assertNotSentTo($this->admin, RequestResolved::class);

        $this->actingAs($this->security)->post("/solicitudes/{$req->id}/aprobar", ['comments' => 'ok']);
        Notification::assertSentTo($this->admin, RequestResolved::class, fn ($n) => $n->approved);
    }

    public function test_rejection_notifies_requester_with_comment(): void
    {
        $this->scenario();
        $req = $this->createRequest();
        Notification::fake();

        $this->actingAs($this->owner)->post("/solicitudes/{$req->id}/rechazar", ['comments' => 'No procede']);

        Notification::assertSentTo($this->admin, RequestResolved::class, fn ($n) => ! $n->approved && $n->comments === 'No procede');
        Notification::assertNotSentTo($this->security, ApprovalPending::class);
    }

    public function test_security_as_current_approver_is_not_alerted_twice(): void
    {
        Notification::fake();
        // Sin owner de app, el primer paso cae en Seguridad.
        $this->scenario('alto', 'baja', ownerless: true);
        $this->createRequest();
        // Recibe "autorización pendiente" y no la alerta duplicada.
        Notification::assertSentTo($this->security, ApprovalPending::class);
        Notification::assertNotSentTo($this->security, HighRiskSodAlert::class);
    }

    public function test_high_risk_alert_goes_to_other_security_managers(): void
    {
        Notification::fake();
        $other = User::factory()->create();
        $other->assignRole('Gestor de Seguridad');
        $this->scenario('alto');
        $this->createRequest();

        // El paso actual es del owner: ambos gestores de seguridad reciben la alerta.
        Notification::assertSentTo($this->security, HighRiskSodAlert::class);
        Notification::assertSentTo($other, HighRiskSodAlert::class);
        Notification::assertNotSentTo($this->admin, HighRiskSodAlert::class); // solicitante
    }

    public function test_low_risk_conflict_sends_no_high_risk_alert(): void
    {
        Notification::fake();
        $this->scenario('bajo');
        $this->createRequest();

        Notification::assertNotSentTo($this->security, HighRiskSodAlert::class);
        Notification::assertNotSentTo($this->admin, HighRiskSodAlert::class);
    }

    public function test_overdue_steps_get_reminders_respecting_interval_and_limit(): void
    {
        $this->scenario('bajo');
        $req = $this->createRequest();
        Notification::fake();

        // Aún dentro del SLA: nada.
        $this->artisan('approvals:remind')->assertSuccessful();
        Notification::assertNothingSent();

        // Vence el SLA: primer recordatorio al owner.
        $this->travel(config('sod.sla.hours') + 1)->hours();
        $this->artisan('approvals:remind')->expectsOutput('Recordatorios enviados: 1');
        Notification::assertSentTo($this->owner, ApprovalPending::class, fn ($n) => $n->reminder);

        // Inmediatamente después: no repite.
        $this->artisan('approvals:remind')->expectsOutput('Recordatorios enviados: 0');

        // Pasado el intervalo: repite, con contador.
        $this->travel(config('sod.sla.reminder_every_hours') + 1)->hours();
        $this->artisan('approvals:remind')->expectsOutput('Recordatorios enviados: 1');
        $this->assertSame(2, $req->approvals()->orderBy('step_order')->first()->reminder_count);
    }

    public function test_reminders_stop_at_max_and_ignore_resolved_requests(): void
    {
        config(['sod.sla.max_reminders' => 1]);
        $this->scenario('bajo');
        $req = $this->createRequest();
        Notification::fake();

        $this->travel(config('sod.sla.hours') + 1)->hours();
        $this->artisan('approvals:remind')->expectsOutput('Recordatorios enviados: 1');

        $this->travel(config('sod.sla.reminder_every_hours') + 1)->hours();
        $this->artisan('approvals:remind')->expectsOutput('Recordatorios enviados: 0'); // tope alcanzado

        // Solicitud ya resuelta: tampoco.
        config(['sod.sla.max_reminders' => 5]);
        $req->update(['status' => RequestStatus::Rechazado]);
        $this->artisan('approvals:remind')->expectsOutput('Recordatorios enviados: 0');
        $this->assertSame(ApprovalStatus::Pendiente, $req->approvals()->first()->status);
    }

    public function test_bell_lists_marks_read_and_isolates_users(): void
    {
        $this->scenario();
        $req = $this->createRequest(); // notifica al owner (cola sync -> se guarda en BD)

        $this->assertSame(1, $this->owner->unreadNotifications()->count());

        $this->actingAs($this->owner)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('notifications.unread', 1)
            ->where('notifications.items.0.title', 'Autorización pendiente'));

        $id = $this->owner->notifications()->first()->id;

        // Otro usuario no puede leer la notificación ajena.
        $this->actingAs($this->security)->post("/notificaciones/{$id}/leer")->assertNotFound();
        $this->assertSame(1, $this->owner->unreadNotifications()->count());

        $this->actingAs($this->owner)->post("/notificaciones/{$id}/leer")
            ->assertRedirect("/solicitudes/{$req->id}");
        $this->assertSame(0, $this->owner->fresh()->unreadNotifications()->count());
    }

    public function test_mark_all_as_read(): void
    {
        $this->scenario();
        $this->createRequest();

        $this->actingAs($this->owner)->post('/notificaciones/leer-todas')->assertRedirect();

        $this->assertSame(0, $this->owner->unreadNotifications()->count());
    }
}
