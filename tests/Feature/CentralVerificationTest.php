<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CentralVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_verification_returns_all_active_term_physical_cards_without_mutation(): void
    {
        [$student, $active, $closed] = $this->context();
        $first = $this->card($student, $active, CardStatus::Released, 'initial');
        $reprint = $this->card($student, $active, CardStatus::ReadyForRelease, 'reprint');
        $this->card($student, $closed, CardStatus::Printed, 'replacement');
        $before = IdCard::pluck('status', 'id')->map(fn ($status) => $status->value)->all();

        $response = $this->actingAs($this->userWith(['cards.view']))->postJson('/scanner/verify', [
            'payload' => $student->student_number,
            'mode' => 'verification',
        ]);

        $response->assertOk()->assertJsonPath('student.student_number', $student->student_number)->assertJsonCount(2, 'cards');
        $this->assertSame([$reprint->id, $first->id], collect($response->json('cards'))->pluck('id')->all());
        $this->assertSame($before, IdCard::pluck('status', 'id')->map(fn ($status) => $status->value)->all());
    }

    public function test_historical_card_token_is_rejected_with_term_feedback(): void
    {
        [$student, , $closed] = $this->context();
        $historical = $this->card($student, $closed, CardStatus::Printed);

        $this->actingAs($this->userWith(['cards.view']))->postJson('/scanner/verify', [
            'payload' => $historical->card_token,
            'mode' => 'verification',
        ])->assertOk()->assertJsonPath('result', 'wrong_term')->assertJsonPath('ok', false)->assertJsonPath('student', null);
    }

    private function context(): array
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $active = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $closed = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'second_semester', 'name' => 'Second Semester', 'status' => 'closed']);
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $student = Student::create(['student_number' => '00000888', 'first_name' => 'Verify', 'last_name' => 'Student']);
        foreach ([$active, $closed] as $term) $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year']);
        return [$student, $active, $closed];
    }

    private function card(Student $student, AcademicTerm $term, CardStatus $status, string $type = 'initial'): IdCard
    {
        return IdCard::create(['student_id' => $student->id, 'academic_year_id' => $term->academic_year_id, 'academic_term_id' => $term->id, 'card_token' => (string) Str::uuid(), 'issuance_type' => $type, 'status' => $status, 'printed_at' => now()]);
    }

    private function userWith(array $slugs): User
    {
        $role = Role::create(['name' => 'Verification role', 'slug' => 'verification-'.Str::random(8)]);
        foreach ($slugs as $slug) $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        $user = User::factory()->create(); $user->roles()->attach($role); return $user;
    }
}