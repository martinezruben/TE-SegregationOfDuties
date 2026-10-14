<?php

use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            '2fa' => EnsureTwoFactorEnabled::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 (token CSRF inválido o sesión expirada): en vez de la página "419 PAGE EXPIRED" sin salida,
        // volvemos a la pantalla anterior con un aviso. Esa redirección crea una sesión y un token nuevos,
        // así que el usuario puede reintentar de inmediato. Patrón recomendado por Inertia.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419 && ! $request->is('api/*')) {
                return back()->with('error', 'Tu sesión expiró o la página quedó desactualizada. Vuelve a intentarlo.');
            }

            return $response;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
