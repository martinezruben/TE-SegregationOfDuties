<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de aprobaciones vencidas (requiere `php artisan schedule:run` en cron cada minuto).
Schedule::command('approvals:remind')->hourly()->withoutOverlapping();
Schedule::command('certifications:remind')->dailyAt('08:00')->withoutOverlapping();
