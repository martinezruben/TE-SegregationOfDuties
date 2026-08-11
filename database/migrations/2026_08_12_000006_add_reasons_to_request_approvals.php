<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Motivos que cubre cada paso de aprobación. Un mismo autorizador
        // consolida varios disparadores en una sola autorización (context.md §8.5).
        Schema::table('request_approvals', function (Blueprint $table) {
            $table->json('reasons')->nullable()->after('approver_role');
        });
    }

    public function down(): void
    {
        Schema::table('request_approvals', function (Blueprint $table) {
            $table->dropColumn('reasons');
        });
    }
};
