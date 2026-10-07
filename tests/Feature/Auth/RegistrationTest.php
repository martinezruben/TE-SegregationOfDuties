<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }

    public function test_root_redirects_by_authentication_state(): void
    {
        $this->get('/')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
