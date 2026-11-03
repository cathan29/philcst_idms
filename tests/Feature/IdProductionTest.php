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

class IdProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_requires_permission_and_renders_empty_state(): void
    {
        $this->actingAs(User::factory()->create())->get('/production')->assertForbidden();
        $this->actingAs($this->userWith(['cards.view']))->get('/production')->assertOk()->assertSee('No ID production requests yet');
    }

    public function test_authorized_staff_creates_pending_request_without_printing(): void
    {
        [$student,$enrollment] = $this->student();
        $user = $this->userWith(['cards.view', 'cards.manage']);
        $this->actingAs($user)->post('/production', ['student_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'issuance_type' => 'initial'])->assertRedirect();
        $card = IdCard::firstOrFail();
        $this->assertSame(CardStatus::Pending, $card->status);
        $this->assertNull($card->printed_at);
        $this->assertSame($user->id, $card->requested_by);
        $this->assertDatabaseHas('id_status_histories', ['id_card_id' => $card->id, 'new_status' => 'pending']);
    }

    public function test_duplicate_active_request_is_prevented(): void
    {
        [$student,$enrollment] = $this->student();
        $user = $this->userWith(['cards.manage']);
        $payload = ['student_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'issuance_type' => 'initial'];
        $this->actingAs($user)->post('/production', $payload)->assertRedirect();
        $this->actingAs($user)->post('/production', $payload)->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('id_cards', 1);
    }

    public function test_existing_released_id_requires_replacement_reason(): void
    {
        [$student,$enrollment] = $this->student();
        IdCard::create(['student_id' => $student->id, 'card_token' => (string) Str::uuid(), 'issuance_type' => 'initial', 'status' => CardStatus::Released]);
        $user = $this->userWith(['cards.manage']);
        $this->actingAs($user)->post('/production', ['student_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'issuance_type' => 'initial'])->assertSessionHasErrors('student_id');
        $this->actingAs($user)->post('/production', ['student_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'issuance_type' => 'lost'])->assertRedirect();
        $this->assertDatabaseHas('id_cards', ['student_id' => $student->id, 'issuance_type' => 'lost', 'status' => 'pending']);
    }

    public function test_print_workflow_records_atomic_timestamps_and_history(): void
    {
        [$card,$user] = $this->cardAndUser();
        foreach (['for_printing', 'printed', 'ready_for_release'] as $status) {
            $this->actingAs($user)->post("/production/{$card->id}/transition", ['status' => $status])->assertRedirect();
        }$card->refresh();
        $this->assertSame(CardStatus::ReadyForRelease, $card->status);
        $this->assertNotNull($card->printing_started_at);
        $this->assertNotNull($card->printed_at);
        $this->assertNotNull($card->ready_at);
        $this->assertSame($user->id, $card->printed_by);
        $this->assertSame(4, $card->histories()->count());
    }

    public function test_unauthorized_user_cannot_confirm_printing(): void
    {
        [$card] = $this->cardAndUser();
        $card->update(['status' => CardStatus::ForPrinting]);
        $this->actingAs($this->userWith(['cards.view']))->post("/production/{$card->id}/transition", ['status' => 'printed'])->assertForbidden();
        $this->assertNull($card->fresh()->printed_at);
    }

    public function test_on_hold_requires_reason_and_resumes_previous_state(): void
    {
        [$card,$user] = $this->cardAndUser();
        $this->actingAs($user)->post("/production/{$card->id}/transition", ['status' => 'on_hold'])->assertSessionHasErrors('status');
        $this->actingAs($user)->post("/production/{$card->id}/transition", ['status' => 'on_hold', 'reason' => 'Printing defect'])->assertRedirect();
        $this->assertSame('pending', $card->fresh()->hold_previous_status);
        $this->actingAs($user)->post("/production/{$card->id}/transition", ['status' => 'for_printing'])->assertSessionHasErrors('status');
        $this->actingAs($user)->post("/production/{$card->id}/transition", ['status' => 'pending'])->assertRedirect();
        $this->assertNotNull($card->fresh()->hold_resolved_at);
    }

    public function test_duplicate_print_confirmation_is_rejected(): void
    {
        [$card,$user] = $this->cardAndUser();
        $this->actingAs($user)->post("/production/{$card->id}/transition", ['status' => 'for_printing']);
        $this->actingAs($user)->post("/production/{$card->id}/transition", ['status' => 'printed'])->assertRedirect();
        $this->actingAs($user)->post("/production/{$card->id}/transition", ['status' => 'printed'])->assertSessionHasErrors('status');
        $this->assertSame(1, $card->histories()->where('new_status', 'printed')->count());
    }

    public function test_bulk_reports_eligible_and_ineligible_transitions(): void
    {
        [$student] = $this->student();
        $user = $this->userWith(['cards.view', 'cards.print']);
        $pending = IdCard::create(['student_id' => $student->id, 'card_token' => (string) Str::uuid(), 'status' => CardStatus::Pending]);
        $printed = IdCard::create(['student_id' => $student->id, 'card_token' => (string) Str::uuid(), 'issuance_type' => 'replacement', 'replacement_sequence' => 1, 'status' => CardStatus::Printed]);
        $this->actingAs($user)->post('/production/bulk', ['card_ids' => [$pending->id, $printed->id], 'status' => 'for_printing'])->assertSessionHas('bulk_result', fn ($r) => $r['eligible'] === 1 && $r['ineligible'] === 1);
        $this->assertSame(CardStatus::ForPrinting, $pending->fresh()->status);
        $this->assertSame(CardStatus::Printed, $printed->fresh()->status);
    }

    public function test_queue_search_status_and_term_filters_are_database_driven(): void
    {
        [$student,$enrollment] = $this->student();
        $user = $this->userWith(['cards.view']);
        $card = IdCard::create(['student_id' => $student->id, 'academic_year_id' => $enrollment->academic_year_id, 'academic_term_id' => $enrollment->academic_term_id, 'card_token' => (string) Str::uuid(), 'status' => CardStatus::Pending, 'requested_at' => now()]);
        $response = $this->actingAs($user)->get('/production?search='.$student->student_number.'&status=pending&academic_term='.$enrollment->academic_term_id);
        $response->assertOk()->assertSee($student->full_name)->assertSee('Pending');
        $this->assertSame($card->id, $response->viewData('cards')->first()->id);
    }

    private function student(): array
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first', 'name' => 'First Semester', 'status' => 'active']);
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $student = Student::create(['student_number' => '000100', 'first_name' => 'Synthetic', 'last_name' => 'Student']);
        $enrollment = $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year', 'section' => 'A']);

        return [$student, $enrollment];
    }

    private function cardAndUser(): array
    {
        [$student,$enrollment] = $this->student();
        $user = $this->userWith(['cards.view', 'cards.manage', 'cards.print']);
        $this->actingAs($user)->post('/production', ['student_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'issuance_type' => 'initial']);

        return [IdCard::firstOrFail(), $user];
    }

    private function userWith(array $slugs): User
    {
        $role = Role::create(['name' => 'Production Test', 'slug' => 'production-'.Str::random(8)]);
        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission);
        }$user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
