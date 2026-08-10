<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Población gobernada (sujetos, sin login). Ver context.md §1.1 y §7.1
        Schema::create('managed_users', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();   // ID de negocio
            $table->string('igg')->unique();           // IGG
            $table->string('name');
            $table->foreignId('area_id')->constrained('areas');
            $table->foreignId('job_position_id')->constrained('job_positions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_users');
    }
};
