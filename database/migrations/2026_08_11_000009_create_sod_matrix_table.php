<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matriz de conflictos SoD entre roles de negocio.
        // Convención: almacenar siempre role_a_id < role_b_id (context.md §7.2)
        Schema::create('sod_matrix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_a_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('role_b_id')->constrained('roles')->cascadeOnDelete();
            $table->string('risk_level')->default('alto'); // alto|medio|bajo
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['role_a_id', 'role_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sod_matrix');
    }
};
