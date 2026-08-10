<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_request_id')->constrained('role_requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_role')->nullable(); // owner_app|jefe|seguridad
            $table->unsignedInteger('step_order');
            $table->string('status')->default('pendiente'); // pendiente|aprobado|rechazado|omitido
            $table->boolean('is_auto')->default(false);
            $table->text('comments')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['role_request_id', 'step_order']);
            $table->index(['approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
    }
};
