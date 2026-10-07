<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla estándar de Laravel para el canal `database` (campana in-app).
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // Seguimiento de SLA por paso de aprobación.
        Schema::table('request_approvals', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('status'); // cuando el paso pasó a ser el actual
            $table->timestamp('reminded_at')->nullable()->after('activated_at');
            $table->unsignedSmallInteger('reminder_count')->default(0)->after('reminded_at');
            $table->index(['status', 'activated_at']);
        });

        // Pasos pendientes ya existentes: el actual (primero pendiente de cada solicitud) corre el SLA desde su creación.
        DB::table('request_approvals')
            ->where('status', 'pendiente')
            ->pluck('role_request_id')
            ->unique()
            ->each(function ($requestId) {
                $first = DB::table('request_approvals')
                    ->where('role_request_id', $requestId)
                    ->where('status', 'pendiente')
                    ->orderBy('step_order')
                    ->first();

                DB::table('request_approvals')->where('id', $first->id)->update(['activated_at' => $first->created_at]);
            });
    }

    public function down(): void
    {
        Schema::table('request_approvals', function (Blueprint $table) {
            $table->dropIndex(['status', 'activated_at']);
            $table->dropColumn(['activated_at', 'reminded_at', 'reminder_count']);
        });
        Schema::dropIfExists('notifications');
    }
};
