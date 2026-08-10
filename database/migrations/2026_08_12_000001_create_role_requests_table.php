<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('managed_user_id')->constrained('managed_users')->cascadeOnDelete();
            $table->foreignId('requested_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('type')->default('asignacion'); // asignacion|movimiento|modificacion_rol
            $table->json('payload')->nullable();
            $table->string('status')->default('pendiente'); // pendiente|en_aprobacion|aprobado|rechazado|cancelado|auto_aprobado
            $table->foreignId('requester_id')->constrained('users');
            $table->text('justification')->nullable();
            $table->boolean('has_sod_conflict')->default(false);
            $table->boolean('is_major')->default(false);
            $table->boolean('auto_resolved')->default(false);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_requests');
    }
};
