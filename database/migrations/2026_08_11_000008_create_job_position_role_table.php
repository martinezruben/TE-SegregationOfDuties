<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reglas de Cargo vs Rol: permitido / no_permitido (context.md §7.2)
        Schema::create('job_position_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_position_id')->constrained('job_positions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('rule_type')->default('permitido'); // permitido|no_permitido
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['job_position_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_position_role');
    }
};
