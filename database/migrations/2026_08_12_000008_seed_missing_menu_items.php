<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inserta de forma idempotente los ítems de menú añadidos después del seed
 * inicial (Autorizadores y Reporte de accesos), para bases de datos ya
 * sembradas donde `MenuSeeder` no se vuelve a ejecutar. No borra datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ubica el grupo "Segregación (SoD)".
        $sodId = DB::table('menus')->where('label', 'Segregación (SoD)')->value('id');
        if ($sodId === null) {
            return; // Estructura de menú no estándar; no forzamos nada.
        }

        $now = now();

        // Orden base: al final de los hijos del grupo.
        $baseOrder = (int) DB::table('menus')->where('parent_id', $sodId)->max('order');

        $items = [
            [
                'label' => 'Autorizadores', 'icon' => 'user-check',
                'route' => 'autorizaciones.index', 'permission_name' => 'autorizaciones.ver',
            ],
            [
                'label' => 'Reporte de accesos', 'icon' => 'table-2',
                'route' => 'reportes.accesos', 'permission_name' => 'reportes.ver',
            ],
        ];

        foreach ($items as $i => $item) {
            $exists = DB::table('menus')
                ->where('parent_id', $sodId)
                ->where('route', $item['route'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('menus')->insert(array_merge($item, [
                'parent_id' => $sodId,
                'order' => $baseOrder + 1 + $i,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('menus')
            ->whereIn('route', ['autorizaciones.index', 'reportes.accesos'])
            ->delete();
    }
};
