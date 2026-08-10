<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_sod_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_request_id')->constrained('role_requests')->cascadeOnDelete();
            $table->foreignId('conflicting_role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('sod_matrix_id')->nullable()->constrained('sod_matrix')->nullOnDelete();
            $table->string('risk_level');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_sod_findings');
    }
};
