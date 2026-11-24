<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\IdRelease;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_require_reports_permission(): void
    {
        $this->get('/reports')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/reports')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/reports/export')->assertForbidden();
    }

    public function test_visible_dataset_and_csv_use_same_active_term_report(): void
    {
        $visible = $this->card('00001', CardStatus::Printed, 'BSIT');
        $released = $this->card('00002', CardStatus::Released, 'BSBA');
        $this->release($released['card']);
        $user = $this->userWith(['reports.view']);
        $query = 'report_type=printed_not_released';

        $this->actingAs($user)->get('/reports?'.$query)
            ->assertOk()->assertSee('Operational Reports')->assertSee('aria-label="Operational report records"', false)
            ->assertSee('The table can be scrolled horizontally on smaller screens.')
            ->assertSee('00001')->assertDontSee('00002');

        $csv = $this->actingAs($user)->get('/reports/export?'.$query);
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('00001', $csv->streamedContent());
        $this->assertStringNotContainsString('00002', $csv->streamedContent());
    }

    public function test_search_date_status_and_report_type_filters_are_supported(): void
    {
        $card = $this->card('00010', CardStatus::Printed, 'BSIT')['card'];
        $card->update(['printed_at' => now()->subDay()]);
        $this->card('00020', CardStatus::Printed, 'BSIT');
        $query = http_build_query(['report_type' => 'printed_cards', 'search' => '00010', 'status' => 'printed', 'date_from' => now()->subDays(2)->toDateString(), 'date_to' => now()->toDateString()]);
        $user = $this->userWith(['reports.view']);

        $this->actingAs($user)->get('/reports?'.$query)->assertOk()->assertSee('00010')->assertDontSee('00020');
        $this->actingAs($user)->get('/reports/print?'.$query)->assertOk()->assertSee('PHILCST Operational Report')->assertSee('00010')->assertDontSee('00020');
        $pdf = $this->actingAs($user)->get('/reports/pdf?'.$query);
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_pdf_export_requires_narrower_filters_above_safe_limit(): void
    {
        config(['idms.report_pdf_limit' => 1]);
        $this->card('00101', CardStatus::Pending, 'BSIT');
        $this->card('00102', CardStatus::Pending, 'BSIT');
        $this->actingAs($this->userWith(['reports.view']))->get('/reports/pdf')->assertUnprocessable()->assertSee('Narrow the report filters');
    }

    public function test_csv_neutralizes_formula_like_student_values(): void
    {
        $this->card('=HYPERLINK("https://example.test")', CardStatus::Pending, 'BSIT');
        $response = $this->actingAs($this->userWith(['reports.view']))->get('/reports/export');
        $response->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    private function card(string $number, CardStatus $status, string $programCode): array
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026-2027']);
        $term = AcademicTerm::firstOrCreate(['academic_year_id' => $year->id, 'code' => 'first_semester'], ['name' => 'First Semester', 'status' => 'active']);
        $program = Program::firstOrCreate(['code' => $programCode], ['name' => $programCode.' Program']);
        $student = Student::create(['student_number' => $number, 'first_name' => 'Report', 'last_name' => $programCode]);
        $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year']);
        $card = IdCard::create(['student_id' => $student->id, 'academic_term_id' => $term->id, 'academic_year_id' => $year->id, 'card_token' => (string) Str::uuid(), 'issuance_type' => 'initial', 'status' => $status, 'printed_at' => in_array($status, [CardStatus::Printed, CardStatus::ReadyForRelease, CardStatus::Released], true) ? now() : null]);
        return compact('student', 'program', 'card', 'year', 'term');
    }

    private function release(IdCard $card): void
    {
        $actor = User::factory()->create();
        $card->update(['released_at' => now(), 'released_by' => $actor->id]);
        IdRelease::create(['id_card_id' => $card->id, 'released_by' => $actor->id, 'released_at' => now(), 'acknowledged_at' => now()]);
    }

    private function userWith(array $slugs): User
    {
        $role = Role::create(['name' => 'Report Test', 'slug' => 'report-'.Str::random(8)]);
        foreach ($slugs as $slug) $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        $user = User::factory()->create(); $user->roles()->attach($role); return $user;
    }
}