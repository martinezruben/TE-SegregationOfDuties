<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La "Foto" actual: qué roles posee cada usuario gestionado
        Schema::create('user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('managed_user_id')->constrained('managed_users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('manual'); // manual|import|request
            $table->timestamp('expires_at')->nullable();  // accesos temporales
            $table->timestamps();

            $table->unique(['managed_user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role');
    }
};
