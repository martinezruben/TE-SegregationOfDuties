<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Activación / desactivación de 2FA desde el perfil del agente.
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    /** Inicia la activación: genera el secreto y muestra el QR en el perfil. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            $this->twoFactor->startSetup($user);
        }

        return back();
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $key = 'two-factor-confirm:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Demasiados intentos. Intenta de nuevo en '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        $codes = $this->twoFactor->confirm($request->user(), $request->input('code'));

        if ($codes === null) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['code' => 'El código no es válido.']);
        }

        RateLimiter::clear($key);

        return back()
            ->with('success', 'Autenticación en dos pasos activada.')
            ->with('recovery_codes', $codes);
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        abort_unless($request->user()->hasTwoFactorEnabled(), 404);

        return back()->with('recovery_codes', $this->twoFactor->regenerateRecoveryCodes($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();

        if ($user->mustUseTwoFactor() && $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'password' => 'Tu rol requiere 2FA: no se puede desactivar.',
            ]);
        }

        $this->twoFactor->disable($user);

        return back()->with('success', 'Autenticación en dos pasos desactivada.');
    }
}
