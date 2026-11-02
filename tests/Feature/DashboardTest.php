<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\IdCard;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_are_database_driven(): void
    {
        $user = User::factory()->create(['name' => 'Ana Reyes']);
        $studentOne = Student::create(['student_number' => '00001', 'first_name' => 'Ana', 'last_name' => 'Reyes']);
        $studentTwo = Student::create(['student_number' => '00002', 'first_name' => 'Ben', 'last_name' => 'Santos']);
        IdCard::create(['student_id' => $studentOne->id, 'card_token' => (string) Str::uuid(), 'status' => CardStatus::Pending]);
        IdCard::create(['student_id' => $studentTwo->id, 'card_token' => (string) Str::uuid(), 'status' => CardStatus::Released, 'released_at' => now()]);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Welcome back, PhilCST')
            ->assertSee('Total Students')
            ->assertSee('Pending Printing')
            ->assertSee('Ready for Release')
            ->assertSee('Released Today')
            ->assertSee('2')
            ->assertSee('Dashboard');
    }
}
