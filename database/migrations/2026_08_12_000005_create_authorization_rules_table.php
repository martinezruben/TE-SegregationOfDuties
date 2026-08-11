<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matriz de autorizaciones configurable (context.md §8.5).
        // Define QUIÉN autoriza según el disparador de una solicitud:
        //  - trigger_type = 'sod_risk'          -> por criticidad de la matriz (alto|medio|bajo)
        //  - trigger_type = 'application'       -> por aplicación
        //  - trigger_type = 'cargo_misalignment'-> por desalineación con el cargo
        // El autorizador puede ser una persona fija (users) o "su supervisor"
        // (el jefe del usuario gestionado).
        Schema::create('authorization_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger_type'); // sod_risk|application|cargo_misalignment
            $table->string('risk_level')->nullable(); // alto|medio|bajo (solo sod_risk)
            $table->foreignId('application_id')->nullable()->constrained('applications')->cascadeOnDelete();
            $table->string('authorizer_type')->default('fixed'); // fixed|supervisor
            $table->foreignId('authorizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['trigger_type', 'is_active']);
            $table->index(['application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_rules');
    }
};
