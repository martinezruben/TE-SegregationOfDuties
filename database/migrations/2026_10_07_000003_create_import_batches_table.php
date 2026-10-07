<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kind'); // foto | gestionados
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('status')->default('preview'); // preview | applied
            $table->unsignedInteger('total_rows')->default(0);
            $table->json('summary')->nullable();   // conteos por estado
            $table->json('preview')->nullable();   // primeras filas analizadas
            $table->json('errors')->nullable();    // errores por fila
            $table->longText('raw_rows');          // filas normalizadas; se re-validan al aplicar
            $table->json('result')->nullable();    // resultado de aplicar
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status']);
        });

        $this->seedPermissionsAndMenu();
    }

    /** Idempotente: permisos `importaciones.*` y la opción de menú, sin tocar nada más. */
    private function seedPermissionsAndMenu(): void
    {
        foreach (['importaciones.ver', 'importaciones.crear'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::where('name', 'Administrador')->where('guard_name', 'web')->first()
            ?->givePermissionTo(['importaciones.ver', 'importaciones.crear']);

        $parentId = DB::table('menus')->where('label', 'Catálogos')->value('id');
        if ($parentId === null || DB::table('menus')->where('route', 'importaciones.index')->exists()) {
            return;
        }

        DB::table('menus')->insert([
            'parent_id' => $parentId,
            'label' => 'Importar datos',
            'icon' => 'upload',
            'route' => 'importaciones.index',
            'permission_name' => 'importaciones.ver',
            'order' => (int) DB::table('menus')->where('parent_id', $parentId)->max('order') + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('route', 'importaciones.index')->delete();
        Schema::dropIfExists('import_batches');
    }
};
