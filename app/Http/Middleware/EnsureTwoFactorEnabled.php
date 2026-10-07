<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a los roles sensibles (config/sod.php) a activar 2FA antes de operar.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->mustUseTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('profile.edit')
                ->with('error', 'Tu rol requiere autenticación en dos pasos. Actívala para continuar.');
        }

        return $next($request);
    }
}
