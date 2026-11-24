<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\IdRelease;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\OperationalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalReportFormulaTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_five_student_scenario_is_reprint_safe(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $students = collect(range(1, 5))->map(function (int $number) use ($year, $term, $program): Student {
            $student = Student::create(['student_number' => sprintf('%08d', $number), 'first_name' => 'Student', 'last_name' => (string) $number]);
            $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year']);
            return $student;
        });
        $actor = User::factory()->create();
        $cards = collect([
            $this->printedCard($students[0], $term, 'initial'),
            $this->printedCard($students[0], $term, 'reprint'),
            $this->printedCard($students[1], $term, 'initial'),
            $this->printedCard($students[2], $term, 'initial'),
        ]);
        foreach ([$cards[0], $cards[2]] as $card) {
            $card->update(['status' => CardStatus::Released, 'released_at' => now(), 'released_by' => $actor->id]);
            IdRelease::create(['id_card_id' => $card->id, 'released_by' => $actor->id, 'released_at' => now(), 'acknowledged_at' => now()]);
        }

        $summary = app(OperationalReportService::class)->summary($term->id);

        $this->assertSame(5, $summary['enrolled_students']);
        $this->assertSame(4, $summary['printed_cards']);
        $this->assertSame(2, $summary['released_cards']);
        $this->assertSame(2, $summary['printed_not_released']);
        $this->assertSame(3, $summary['unique_students_printed']);
        $this->assertSame(2, $summary['students_without_printed']);
        $this->assertSame(2, $summary['unique_students_released']);
        $this->assertSame(3, $summary['students_without_released']);
    }

    public function test_report_datasets_match_summary_populations(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $student = Student::create(['student_number' => '00000001', 'first_name' => 'Only', 'last_name' => 'Student']);
        $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year']);
        $service = app(OperationalReportService::class);

        $this->assertSame(1, $service->enrollments($term->id)->count());
        $this->assertSame(0, $service->printedCards($term->id)->count());
        $this->assertSame(0, $service->releasedCards($term->id)->count());
        $this->assertSame(1, $service->studentsWithoutPrinted($term->id)->count());
        $this->assertSame(1, $service->studentsWithoutReleased($term->id)->count());
    }

    private function printedCard(Student $student, AcademicTerm $term, string $type): IdCard
    {
        return IdCard::create([
            'student_id' => $student->id,
            'academic_year_id' => $term->academic_year_id,
            'academic_term_id' => $term->id,
            'card_token' => (string) Str::uuid(),
            'issuance_type' => $type,
            'status' => CardStatus::Printed,
            'printed_at' => now(),
        ]);
    }
}