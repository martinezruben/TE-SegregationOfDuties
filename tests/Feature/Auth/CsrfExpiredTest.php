<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los tests de Laravel desactivan la verificación CSRF; aquí se reactiva para comprobar qué ocurre
 * cuando el token no coincide o la sesión expiró (el usuario veía un "419 PAGE EXPIRED" sin salida).
 */
class CsrfExpiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app->make(Encrypter::class)) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_login_with_expired_session_redirects_back_with_a_message_instead_of_419(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect('/login');
        $response->assertSessionHas('error', fn ($m) => str_contains($m, 'expiró'));
        $this->assertGuest();
    }

    public function test_the_redirect_also_works_for_inertia_requests(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHas('error');
    }

    public function test_the_flash_message_reaches_the_login_page(): void
    {
        $this->withSession(['error' => 'Tu sesión expiró o la página quedó desactualizada. Vuelve a intentarlo.'])
            ->get('/login')
            ->assertInertia(fn ($page) => $page->component('Auth/Login')->where('flash.error', fn ($m) => str_contains($m, 'expiró')));
    }

    public function test_retrying_with_a_valid_token_logs_in(): void
    {
        $user = User::factory()->create();

        $this->withSession(['_token' => 'token-valido'])
            ->post('/login', ['_token' => 'token-valido', 'email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_invalid_token_does_not_log_in_even_with_correct_credentials(): void
    {
        $user = User::factory()->create();

        $this->withSession(['_token' => 'token-valido'])
            ->from('/login')
            ->post('/login', ['_token' => 'otro-token', 'email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_authenticated_posts_with_expired_token_keep_the_session_and_warn(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->post('/logout')
            ->assertRedirect('/profile')
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($user);
    }
}
