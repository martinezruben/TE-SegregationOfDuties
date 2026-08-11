<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enlaza cada asignación de la "foto" con la solicitud que la otorgó,
        // para el reporte de accesos (número de solicitud y fechas). Ver §8.9.
        Schema::table('user_role', function (Blueprint $table) {
            $table->foreignId('role_request_id')->nullable()->after('source')
                ->constrained('role_requests')->nullOnDelete();
        });

        // Backfill best-effort para asignaciones ya existentes originadas en solicitudes:
        // toma la última solicitud aprobada/auto-aprobada del mismo usuario y rol.
        $rows = DB::table('user_role')->where('source', 'request')->get(['id', 'managed_user_id', 'role_id']);
        foreach ($rows as $row) {
            $reqId = DB::table('role_requests')
                ->where('managed_user_id', $row->managed_user_id)
                ->where('requested_role_id', $row->role_id)
                ->whereIn('status', ['aprobado', 'auto_aprobado'])
                ->orderByDesc('id')
                ->value('id');

            if ($reqId !== null) {
                DB::table('user_role')->where('id', $row->id)->update(['role_request_id' => $reqId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('user_role', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_request_id');
        });
    }
};
