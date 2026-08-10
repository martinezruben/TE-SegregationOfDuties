<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opciones/Acciones dentro de un rol (ej. "Crear Proveedor")
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('name');
            $table->string('action_key');
            $table->timestamps();

            $table->unique(['role_id', 'action_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
