<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_secure_institutional_form(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('PHILCST IDMS')
            ->assertSee('Email Address')
            ->assertSee('Password')
            ->assertSee('Authorized PhilCST Personnel Only')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertDontSee('admin@idmonitor.local')
            ->assertDontSee('Demo:')
            ->assertDontSee('Sign Up');
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_invalid_credentials_return_safe_general_error(): void
    {
        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'incorrect-password'])
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);

        $this->assertGuest();
    }
}
