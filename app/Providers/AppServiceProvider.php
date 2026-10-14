<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configureHttps();
    }

    /**
     * Detrás de un proxy que termina HTTPS, las URLs generadas deben salir en https://
     * (ver config/sod.php, sección `http`).
     */
    private function configureHttps(): void
    {
        if ($proxies = config('sod.http.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', (string) $proxies)));
        }

        $force = config('sod.http.force_https');
        $force = $force === null
            ? str_starts_with((string) config('app.url'), 'https://')
            : filter_var($force, FILTER_VALIDATE_BOOLEAN);

        if ($force) {
            URL::forceScheme('https');
        }
    }
}
