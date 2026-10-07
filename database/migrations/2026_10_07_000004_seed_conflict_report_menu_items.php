<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inserta de forma idempotente los ítems de menú de los reportes de conflictos
 * activos y de excepciones, para bases ya sembradas. No borra datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sodId = DB::table('menus')->where('label', 'Segregación (SoD)')->value('id');
        if ($sodId === null) {
            return;
        }

        $order = (int) DB::table('menus')->where('parent_id', $sodId)->max('order');
        $items = [
            ['label' => 'Conflictos activos', 'icon' => 'shield-alert', 'route' => 'reportes.conflictos'],
            ['label' => 'Excepciones', 'icon' => 'file-warning', 'route' => 'reportes.excepciones'],
        ];

        foreach ($items as $i => $item) {
            if (DB::table('menus')->where('parent_id', $sodId)->where('route', $item['route'])->exists()) {
                continue;
            }

            DB::table('menus')->insert($item + [
                'parent_id' => $sodId,
                'permission_name' => 'reportes.ver',
                'order' => $order + 1 + $i,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('route', ['reportes.conflictos', 'reportes.excepciones'])->delete();
    }
};
