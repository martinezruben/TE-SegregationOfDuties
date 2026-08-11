<?php

namespace App\Console\Commands;

use App\Models\Menu;
use Database\Seeders\MenuSeeder;
use Illuminate\Console\Command;

/**
 * Reconstruye el menú lateral desde cero: borra todos los ítems y vuelve a
 * sembrar la estructura oficial (MenuSeeder). Útil para limpiar duplicados.
 */
class RebuildMenu extends Command
{
    protected $signature = 'menu:rebuild {--force : No pedir confirmación}';

    protected $description = 'Borra y regenera el menú lateral desde el MenuSeeder (limpia duplicados)';

    public function handle(): int
    {
        $count = Menu::count();

        if (! $this->option('force') && ! $this->confirm("Se borrarán los {$count} ítems de menú actuales y se regenerarán. ¿Continuar?", true)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        // cascadeOnDelete se encarga de los hijos; borramos todo por si acaso.
        Menu::query()->delete();
        $this->call('db:seed', ['--class' => MenuSeeder::class, '--force' => true]);

        $this->info('Menú regenerado: '.Menu::count().' ítems.');

        return self::SUCCESS;
    }
}
