<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('abierta'); // abierta | cerrada
            $table->date('due_date');
            $table->json('filters')->nullable(); // application_id, area_id, only_conflicts
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('certification_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('certification_campaigns')->cascadeOnDelete();
            $table->foreignId('managed_user_id')->constrained('managed_users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();

            // Snapshot al momento de generar la campaña (evidencia para auditoría).
            $table->string('user_name');
            $table->string('user_igg');
            $table->string('area')->nullable();
            $table->string('cargo')->nullable();
            $table->string('app_name');
            $table->string('role_name');
            $table->string('source')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('conflict_risk')->nullable(); // mayor riesgo SoD en el que participa este acceso

            $table->string('decision')->default('pendiente'); // pendiente | mantener | revocar
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'managed_user_id', 'role_id']);
            $table->index(['campaign_id', 'decision']);
            $table->index(['reviewer_id', 'decision']);
        });

        $this->seedPermissionsAndMenu();
    }

    /** Idempotente: permisos `certificaciones.*`, asignación a roles de plantilla y opción de menú. */
    private function seedPermissionsAndMenu(): void
    {
        $perms = ['certificaciones.ver', 'certificaciones.crear', 'certificaciones.revisar', 'certificaciones.auditar'];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $grants = [
            'Administrador' => $perms,
            'Gestor de Seguridad' => $perms,
            'Owner de Aplicación' => ['certificaciones.ver', 'certificaciones.revisar'],
            'Jefe' => ['certificaciones.ver', 'certificaciones.revisar'],
            'Auditor' => ['certificaciones.ver', 'certificaciones.auditar'],
        ];
        foreach ($grants as $roleName => $names) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($names);
        }

        $sodId = DB::table('menus')->where('label', 'Segregación (SoD)')->value('id');
        if ($sodId !== null && ! DB::table('menus')->where('route', 'certificaciones.index')->exists()) {
            DB::table('menus')->insert([
                'parent_id' => $sodId,
                'label' => 'Recertificación',
                'icon' => 'badge-check',
                'route' => 'certificaciones.index',
                'permission_name' => 'certificaciones.ver',
                'order' => (int) DB::table('menus')->where('parent_id', $sodId)->max('order') + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('route', 'certificaciones.index')->delete();
        Schema::dropIfExists('certification_items');
        Schema::dropIfExists('certification_campaigns');
    }
};
