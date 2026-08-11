<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El "jefe" del usuario gestionado. Es un agente (users) que puede
        // autorizar, para poder usarse como "su supervisor" en la matriz de
        // autorizaciones. Ver context.md §8.5.
        Schema::table('managed_users', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('job_position_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('managed_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
        });
    }
};
