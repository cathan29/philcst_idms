<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'Valid-password-123!', 'is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Valid-password-123!'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_authenticated_session_is_terminated(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }
}
