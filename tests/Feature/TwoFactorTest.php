<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function otp(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
    }

    /** Usuario con 2FA ya activado; devuelve [usuario, códigos de recuperación]. */
    private function userWithTwoFactor(string $password = 'password'): array
    {
        $user = User::factory()->create(['password' => $password]);
        $service = app(TwoFactorService::class);
        $service->startSetup($user);
        $codes = $service->confirm($user, $this->otp($user));
        // Evita que el intervalo TOTP ya consumido al activar bloquee el siguiente login.
        $user->forceFill(['two_factor_last_timestamp' => null])->save();

        return [$user->fresh(), $codes];
    }

    public function test_user_can_enable_two_factor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/profile/two-factor')->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());

        $this->post('/profile/two-factor/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $this->post('/profile/two-factor/confirm', ['code' => $this->otp($user)])
            ->assertSessionHas('recovery_codes');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertCount(TwoFactorService::RECOVERY_CODES, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_secrets_are_encrypted_and_hidden(): void
    {
        [$user] = $this->userWithTwoFactor();

        $raw = \DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame($user->two_factor_secret, $raw);
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
    }

    public function test_login_requires_challenge_when_two_factor_is_enabled(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->post('/two-factor-challenge', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->otp($user)])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_totp_code_cannot_be_reused(): void
    {
        [$user] = $this->userWithTwoFactor();
        $code = $this->otp($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect();
        $this->assertAuthenticated();

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once(): void
    {
        [$user, $codes] = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertCount(TwoFactorService::RECOVERY_CODES - 1, $user->fresh()->two_factor_recovery_codes);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_challenge_is_rate_limited(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge', ['code' => '000000']);
        }

        // Aun con el código correcto, queda bloqueado.
        $this->post('/two-factor-challenge', ['code' => $this->otp($user)])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_page_without_pending_login_redirects(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
    }

    public function test_disable_requires_password_for_optional_roles(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->actingAs($user);

        $this->delete('/profile/two-factor', ['password' => 'incorrecta'])
            ->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->delete('/profile/two-factor', ['password' => 'password'])->assertRedirect();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_sensitive_roles_must_enable_two_factor(): void
    {
        config(['sod.two_factor.enforce' => true]);
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Administrador');
        $this->actingAs($admin);

        $this->get('/areas')->assertRedirect(route('profile.edit'));
        $this->get('/dashboard')->assertRedirect(route('profile.edit'));
        $this->get('/profile')->assertOk();

        $this->post('/profile/two-factor');
        $this->post('/profile/two-factor/confirm', ['code' => $this->otp($admin)]);

        $this->get('/areas')->assertOk();

        // Obligatorio: no se puede desactivar.
        $this->delete('/profile/two-factor', ['password' => 'password'])
            ->assertSessionHasErrors('password');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_optional_roles_are_not_forced(): void
    {
        config(['sod.two_factor.enforce' => true]);
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Solicitante');
        $this->actingAs($user);

        $this->get('/dashboard')->assertOk();
    }
}
