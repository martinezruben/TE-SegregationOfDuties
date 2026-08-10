<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1) RBAC: permisos y roles de plataforma.
        $this->call(RolePermissionSeeder::class);

        // 2) Agente administrador de prueba (login).
        $admin = User::updateOrCreate(
            ['email' => 'admin@sod.test'],
            ['name' => 'Administrador', 'password' => Hash::make('password')],
        );
        $admin->syncRoles(['Administrador']);

        // 2b) Gestor de Seguridad de prueba (aprobador de autorizaciones mayores/altas).
        $seguridad = User::updateOrCreate(
            ['email' => 'seguridad@sod.test'],
            ['name' => 'Gestor de Seguridad', 'password' => Hash::make('password')],
        );
        $seguridad->syncRoles(['Gestor de Seguridad']);

        // 3) Menú lateral.
        $this->call(MenuSeeder::class);

        // 4) Datos demo (áreas, cargos, apps, roles, matriz, usuarios gestionados).
        $this->call(DemoDataSeeder::class);
    }
}
