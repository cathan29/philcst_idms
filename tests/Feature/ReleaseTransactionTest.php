<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\CardStatusTransitionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReleaseTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_creates_one_transaction_for_the_exact_physical_card(): void
    {
        [$active, $student] = $this->context();
        $older = $this->card($student, $active, CardStatus::Released, 'replacement');
        $card = $this->card($student, $active, CardStatus::ReadyForRelease, 'reprint');
        $actor = User::factory()->create();

        app(CardStatusTransitionService::class)->transition($card, CardStatus::Released, $actor, null, [
            'claimant_name' => 'Registered Student',
            'claimant_relationship' => 'Self',
            'release_remarks' => 'School ID checked.',
        ]);

        $this->assertDatabaseHas('id_releases', [
            'id_card_id' => $card->id,
            'released_by' => $actor->id,
            'claimant_name' => 'Registered Student',
            'claimant_relationship' => 'Self',
            'remarks' => 'School ID checked.',
        ]);
        $this->assertDatabaseMissing('id_releases', ['id_card_id' => $older->id]);
        $this->assertNotNull($card->fresh()->release);
        $this->assertSame(CardStatus::Released, $card->fresh()->status);
    }

    public function test_release_rejects_card_from_non_active_term_without_mutation(): void
    {
        [$active, $student, $year] = $this->context();
        $closed = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'second_semester', 'name' => 'Second Semester', 'status' => 'closed']);
        $card = $this->card($student, $closed, CardStatus::ReadyForRelease);

        try {
            app(CardStatusTransitionService::class)->transition($card, CardStatus::Released, User::factory()->create());
            $this->fail('Expected a wrong-term release to be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('active academic term', $exception->getMessage());
        }

        $this->assertSame(CardStatus::ReadyForRelease, $card->fresh()->status);
        $this->assertDatabaseMissing('id_releases', ['id_card_id' => $card->id]);
    }

    public function test_duplicate_release_transaction_is_rejected_atomically(): void
    {
        [$active, $student] = $this->context();
        $card = $this->card($student, $active, CardStatus::ReadyForRelease);
        $actor = User::factory()->create();
        $service = app(CardStatusTransitionService::class);
        $service->transition($card, CardStatus::Released, $actor);

        $this->expectException(DomainException::class);
        $service->transition($card->fresh(), CardStatus::Released, $actor);
    }

    private function context(): array
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $student = Student::create(['student_number' => '00009999', 'first_name' => 'Release', 'last_name' => 'Student']);
        $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year']);

        return [$term, $student, $year];
    }

    private function card(Student $student, AcademicTerm $term, CardStatus $status, string $type = 'initial'): IdCard
    {
        return IdCard::create([
            'student_id' => $student->id,
            'academic_year_id' => $term->academic_year_id,
            'academic_term_id' => $term->id,
            'card_token' => (string) Str::uuid(),
            'issuance_type' => $type,
            'status' => $status,
            'requested_at' => now(),
            'printed_at' => in_array($status, [CardStatus::ReadyForRelease, CardStatus::Released], true) ? now() : null,
        ]);
    }
}