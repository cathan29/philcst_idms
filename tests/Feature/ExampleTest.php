<?php

namespace Tests\Feature;

use App\Models\IdHolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_admin_can_log_in_and_open_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertOk()->assertSee('Dashboard');
    }

    public function test_scanning_an_active_id_toggles_entry_and_exit(): void
    {
        $user = User::factory()->create();
        $holder = IdHolder::create([
            'id_number' => 'TEST-001',
            'first_name' => 'Test',
            'last_name' => 'Holder',
            'holder_type' => 'student',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post('/scan', ['id_number' => 'test-001'])->assertSessionHas('scan_success');
        $this->assertDatabaseHas('scan_logs', ['id_holder_id' => $holder->id, 'action' => 'entry', 'status' => 'accepted']);

        $this->travel(21)->seconds();
        $this->actingAs($user)->post('/scan', ['id_number' => 'TEST-001'])->assertSessionHas('scan_success');
        $this->assertDatabaseHas('scan_logs', ['id_holder_id' => $holder->id, 'action' => 'exit', 'status' => 'accepted']);
    }

    public function test_unknown_id_is_logged_as_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/scan', ['id_number' => 'UNKNOWN'])->assertSessionHas('scan_error');
        $this->assertDatabaseHas('scan_logs', ['scanned_code' => 'UNKNOWN', 'action' => 'denied', 'status' => 'rejected']);
    }
}
