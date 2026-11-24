<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\IdRelease;
use App\Models\IdStatusHistory;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_are_scoped_to_the_active_term(): void
    {
        $user = User::factory()->create(['name' => 'Ana Reyes']);
        $year = AcademicYear::create(['name' => '2026-2027']);
        $active = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $closed = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'second_semester', 'name' => 'Second Semester', 'status' => 'closed']);
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $activeStudent = Student::create(['student_number' => '00001', 'first_name' => 'Ana', 'last_name' => 'Reyes']);
        $historicalStudent = Student::create(['student_number' => '00002', 'first_name' => 'Ben', 'last_name' => 'Santos']);
        $activeStudent->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $active->id, 'semester' => $active->name, 'year_level' => '1st Year']);
        $historicalStudent->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $closed->id, 'semester' => $closed->name, 'year_level' => '1st Year']);
        IdCard::create(['student_id' => $activeStudent->id, 'academic_year_id' => $year->id, 'academic_term_id' => $active->id, 'card_token' => (string) Str::uuid(), 'status' => CardStatus::Pending]);
        IdCard::create(['student_id' => $historicalStudent->id, 'academic_year_id' => $year->id, 'academic_term_id' => $closed->id, 'card_token' => (string) Str::uuid(), 'status' => CardStatus::Pending]);
        $released = IdCard::create(['student_id' => $activeStudent->id, 'academic_year_id' => $year->id, 'academic_term_id' => $active->id, 'card_token' => (string) Str::uuid(), 'issuance_type' => 'replacement', 'replacement_sequence' => 1, 'status' => CardStatus::Released, 'printed_at' => now()]);
        IdRelease::create(['id_card_id' => $released->id, 'released_by' => $user->id, 'released_at' => now(), 'acknowledged_at' => now()]);
        IdStatusHistory::create(['id_card_id' => $released->id, 'previous_status' => 'ready_for_release', 'new_status' => CardStatus::Released, 'user_id' => $user->id, 'occurred_at' => now()]);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('totalStudents', 1)
            ->assertViewHas('pendingPrinting', 1)
            ->assertViewHas('releasedToday', 1)
            ->assertSee('Dashboard');
    }
}