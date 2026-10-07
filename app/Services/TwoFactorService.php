<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Autenticación en dos pasos TOTP (RFC 6238) con códigos de recuperación.
 */
class TwoFactorService
{
    public const RECOVERY_CODES = 8;

    public function __construct(private Google2FA $google2fa) {}

    /** Genera y guarda un secreto nuevo (sin confirmar) para el usuario. */
    public function startSetup(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(32),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
        ])->save();
    }

    public function qrCodeSvg(User $user): string
    {
        $url = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret,
        );

        return (new Writer(new ImageRenderer(
            new RendererStyle(192, 0),
            new SvgImageBackEnd,
        )))->writeString($url);
    }

    /** Verifica un código TOTP y registra el intervalo para impedir su reutilización. */
    public function verifyCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        $timestamp = $this->google2fa->verifyKeyNewer(
            $user->two_factor_secret,
            preg_replace('/\s+/', '', $code),
            $user->two_factor_last_timestamp ?? 0, // con null devuelve true y no el intervalo
        );

        if ($timestamp === false || ! is_int($timestamp)) {
            return false;
        }

        $user->forceFill(['two_factor_last_timestamp' => $timestamp])->save();

        return true;
    }

    /** Confirma la activación y devuelve los códigos de recuperación (se muestran una sola vez). */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || $user->hasTwoFactorEnabled()) {
            return null;
        }

        if (! $this->verifyCode($user, $code)) {
            return null;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->regenerateRecoveryCodes($user);
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    /** Consume un código de recuperación (un solo uso). */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $code)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
        ])->save();
    }
}
