<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\ScanLog;
use App\Models\Student;
use App\Models\User;
use App\Services\ScanVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_requires_card_view_permission_and_renders_workstation(): void
    {
        $this->get('/scanner')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/scanner')->assertForbidden();
        $this->actingAs($this->userWith(['cards.view']))->get('/scanner')->assertOk()->assertSee('Ready for student number')->assertSee('Enter or scan a student number, then press Enter.')->assertSee('Student number or card identifier')->assertSee('USB scanners work like a keyboard.')->assertSee('Camera QR scanner')->assertSee('No scan activity yet.')->assertDontSee('View All Scan History')->assertSee('Latest active-term verification attempts')->assertSee('qr-scanner-');
    }

    public function test_valid_token_and_student_number_lookup_are_logged(): void
    {
        [$student, $card] = $this->card(CardStatus::Printed);
        $user = $this->userWith(['cards.view']);
        $this->actingAs($user)->postJson('/scanner/verify', ['payload' => $card->card_token, 'mode' => 'verification'])->assertOk()->assertJsonPath('card.status', 'printed')->assertJsonPath('student.name', $student->full_name)->assertJsonPath('enrollment.program', 'BSIT')->assertJsonPath('card.issuance_type', 'Initial');
        $this->travel(3)->seconds();
        $this->actingAs($user)->postJson('/scanner/verify', ['payload' => $student->student_number, 'mode' => 'verification'])->assertOk()->assertJsonPath('student.student_number', $student->student_number);
        $this->assertDatabaseCount('scan_logs', 2);
        $this->assertNotSame($card->card_token, ScanLog::first()->scanned_code);
    }

    public function test_invalid_unknown_and_student_without_card_are_clear(): void
    {
        [$student] = $this->student('SYN-200');
        $user = $this->userWith(['cards.view']);
        $this->actingAs($user)->postJson('/scanner/verify', ['payload' => 'https://evil.example/x', 'mode' => 'verification'])->assertOk()->assertJsonPath('result', 'invalid');
        $this->actingAs($user)->postJson('/scanner/verify', ['payload' => 'UNKNOWN-999', 'mode' => 'verification'])->assertOk()->assertJsonPath('result', 'not_found');
        $this->actingAs($user)->postJson('/scanner/verify', ['payload' => $student->student_number, 'mode' => 'verification'])->assertOk()->assertJsonPath('result', 'no_card');
    }

    public function test_student_number_with_multiple_cards_returns_complete_card_history(): void
    {
        [$student] = $this->student('SYN-201');
        $this->makeCard($student, CardStatus::Released);
        $this->makeCard($student, CardStatus::Revoked);
        $this->actingAs($this->userWith(['cards.view']))
            ->postJson('/scanner/verify', ['payload' => $student->student_number, 'mode' => 'verification'])
            ->assertOk()
            ->assertJsonCount(2, 'cards');
    }

    public function test_all_operational_statuses_are_reported_without_transition(): void
    {
        $user = $this->userWith(['cards.view']);
        foreach ([CardStatus::Pending, CardStatus::Printed, CardStatus::ReadyForRelease, CardStatus::Released, CardStatus::Revoked, CardStatus::OnHold] as $index => $status) {
            [, $card] = $this->card($status, 'SYN-3'.$index);
            $this->actingAs($user)->postJson('/scanner/verify', ['payload' => $card->card_token, 'mode' => 'verification'])->assertOk()->assertJsonPath('card.status', $status->value);
            $this->assertSame($status, $card->fresh()->status);
        }
    }

    public function test_rapid_duplicate_scan_is_recorded_without_status_change(): void
    {
        [, $card] = $this->card(CardStatus::ReadyForRelease);
        $user = $this->userWith(['cards.view']);
        $payload = ['payload' => $card->card_token, 'mode' => 'verification'];
        $this->actingAs($user)->postJson('/scanner/verify', $payload)->assertOk();
        $this->actingAs($user)->postJson('/scanner/verify', $payload)->assertOk()->assertJsonPath('result', 'duplicate');
        $this->assertSame(CardStatus::ReadyForRelease, $card->fresh()->status);
    }

    public function test_mode_permissions_and_transition_permissions_are_enforced(): void
    {
        [, $card] = $this->card(CardStatus::ForPrinting);
        $viewer = $this->userWith(['cards.view']);
        $this->actingAs($viewer)->postJson('/scanner/verify', ['payload' => $card->card_token, 'mode' => 'production'])->assertForbidden();
        $this->actingAs($viewer)->postJson("/scanner/cards/{$card->id}/transition", ['mode' => 'production', 'target' => 'printed'])->assertForbidden();
    }

    public function test_authorized_production_and_release_transitions_use_existing_lifecycle(): void
    {
        [, $printing] = $this->card(CardStatus::ForPrinting, 'SYN-401');
        $printer = $this->userWith(['cards.view', 'cards.print']);
        $this->actingAs($printer)->postJson("/scanner/cards/{$printing->id}/transition", ['mode' => 'production', 'target' => 'printed'])->assertOk();
        $this->assertSame(CardStatus::Printed, $printing->fresh()->status);
        [, $ready] = $this->card(CardStatus::ReadyForRelease, 'SYN-402');
        $releaser = $this->userWith(['cards.view', 'cards.release']);
        $this->actingAs($releaser)->postJson("/scanner/cards/{$ready->id}/transition", ['mode' => 'releasing', 'target' => 'released', 'release_acknowledged' => true, 'claimant_name' => 'Authorized Claimant', 'claimant_relationship' => 'Self', 'release_remarks' => 'Identity checked.'])->assertOk();
        $this->assertSame($releaser->id, $ready->fresh()->released_by);
        $this->assertNotNull($ready->fresh()->release_acknowledged_at);
        $this->assertSame('Authorized Claimant', $ready->fresh()->claimant_name);
        $this->assertSame('Self', $ready->fresh()->claimant_relationship);
        $this->assertSame('Identity checked.', $ready->fresh()->release_remarks);
        $this->actingAs($releaser)
            ->get("/production/{$ready->id}")
            ->assertOk()
            ->assertSee('Release Transaction')
            ->assertSee('Authorized Claimant')
            ->assertSee('Identity checked.');
        $this->actingAs($this->userWith(['cards.view']))
            ->get("/production/{$ready->id}")
            ->assertOk()
            ->assertDontSee('Authorized Claimant');
        $this->actingAs($releaser)->postJson("/scanner/cards/{$ready->id}/transition", ['mode' => 'releasing', 'target' => 'released'])->assertUnprocessable();
    }

    public function test_release_requires_explicit_acknowledgement(): void
    {
        [, $card] = $this->card(CardStatus::ReadyForRelease, 'SYN-ACK');
        $releaser = $this->userWith(['cards.view', 'cards.release']);

        $this->actingAs($releaser)->postJson("/scanner/cards/{$card->id}/transition", [
            'mode' => 'releasing',
            'target' => 'released',
        ])->assertUnprocessable()->assertJsonValidationErrors('release_acknowledged');

        $this->assertSame(CardStatus::ReadyForRelease, $card->fresh()->status);
    }
    public function test_recent_activity_is_limited_to_five_and_links_to_full_history(): void
    {
        $user = $this->userWith(['cards.view', 'audit.view']);
        foreach (range(1, 6) as $index) {
            ScanLog::create([
                'operator_id' => $user->id,
                'station_mode' => 'verification',
                'scanned_code' => "hash-{$index}",
                'action' => 'verify',
                'status' => 'rejected',
                'result' => 'not_found',
                'scanned_at' => now()->addSeconds($index),
            ]);
        }

        $this->actingAs($user)->get('/scanner')
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 5)
            ->assertSee(route('logs.index'), false)
            ->assertSee('View All Scan History');
    }

    public function test_only_scanner_navigation_is_active_on_scanner_route(): void
    {
        $this->actingAs($this->userWith(['cards.view', 'audit.view']))->get('/scanner')
            ->assertOk()
            ->assertSee('class="active" href="'.route('scanner.index').'"', false)
            ->assertDontSee('class="active" href="'.route('production.index').'"', false);
    }

    public function test_empty_and_whitespace_payloads_are_rejected_without_logging(): void
    {
        $user = $this->userWith(['cards.view', 'audit.view']);
        $this->actingAs($user)->postJson('/scanner/verify', ['payload' => '', 'mode' => 'verification'])->assertUnprocessable();
        $this->actingAs($user)->postJson('/scanner/verify', ['payload' => '   ', 'mode' => 'verification'])->assertUnprocessable();
        $this->assertDatabaseCount('scan_logs', 0);
    }

    public function test_leading_zero_student_identifier_is_preserved(): void
    {
        [$student, $card] = $this->card(CardStatus::Printed, '00001234');
        $this->actingAs($this->userWith(['cards.view', 'audit.view']))
            ->postJson('/scanner/verify', ['payload' => '  00001234  ', 'mode' => 'verification'])
            ->assertOk()
            ->assertJsonPath('student.student_number', '00001234')
            ->assertJsonPath('card.id', $card->id);
        $this->assertSame('00001234', $student->fresh()->student_number);
    }

    public function test_server_failure_returns_recoverable_error_without_false_success(): void
    {
        $mock = \Mockery::mock(ScanVerificationService::class);
        $mock->shouldReceive('verify')->once()->andThrow(new \RuntimeException('Synthetic database failure'));
        $this->app->instance(ScanVerificationService::class, $mock);
        $this->actingAs($this->userWith(['cards.view', 'audit.view']))
            ->postJson('/scanner/verify', ['payload' => 'SYN-ERROR', 'mode' => 'verification'])
            ->assertStatus(500)
            ->assertJson(['ok' => false, 'result' => 'error']);
        $this->assertDatabaseCount('scan_logs', 0);
    }

    public function test_client_implements_authoritative_input_states_and_submission_guards(): void
    {
        $script = file_get_contents(resource_path('js/qr-scanner.js'));
        foreach (['IDLE', 'INPUT_DETECTED', 'VERIFYING', 'VERIFIED', 'NOT_FOUND', 'INVALID', 'ERROR'] as $state) {
            $this->assertStringContainsString("{$state}: '{$state}'", $script);
        }
        $this->assertStringContainsString("form.addEventListener('submit'", $script);
        $this->assertStringContainsString("input.addEventListener('input'", $script);
        $this->assertStringContainsString('input.focus()', $script);
        $this->assertStringContainsString('input.readOnly = verifying', $script);
        $this->assertStringContainsString('if (lastResult) render(lastResult);', $script);
        $this->assertStringNotContainsString('setTimeout(', $script);
        $this->assertStringNotContainsString('parseInt(', $script);
    }

    public function test_scanner_recent_activity_hides_other_operators_records_without_audit_permission(): void
    {
        $viewer = $this->userWith(['cards.view']);
        $other = User::factory()->create();
        [$ownStudent, $ownCard] = $this->card(CardStatus::Printed, 'OWN-100');
        [$otherStudent, $otherCard] = $this->card(CardStatus::Printed, 'OTHER-200');

        foreach ([[$viewer, $ownStudent, $ownCard], [$other, $otherStudent, $otherCard]] as [$operator, $student, $card]) {
            ScanLog::create([
                'student_id' => $student->id,
                'id_card_id' => $card->id,
                'operator_id' => $operator->id,
                'station_mode' => 'verification',
                'scanned_code' => 'redacted...',
                'payload_hash' => hash('sha256', $student->student_number),
                'action' => 'verify',
                'status' => 'accepted',
                'result' => 'printed',
                'scanned_at' => now(),
            ]);
        }

        $this->actingAs($viewer)->get('/scanner')
            ->assertOk()
            ->assertSee('OWN-100')
            ->assertDontSee('OTHER-200');
    }
    public function test_scanner_exposes_camera_controls_and_lifecycle_verification_details(): void
    {
        [$student, $card] = $this->card(CardStatus::Released, 'SYN-501');
        $card->forceFill([
            'printed_at' => now()->subDay(),
            'ready_at' => now()->subHours(12),
            'released_at' => now(),
        ])->save();

        $user = $this->userWith(['cards.view', 'audit.view']);
        $this->actingAs($user)->get('/scanner')
            ->assertOk()
            ->assertSee('Start camera')
            ->assertSee('Camera preview');

        $this->actingAs($user)->postJson('/scanner/verify', [
            'payload' => $card->card_token,
            'mode' => 'verification',
        ])->assertOk()
            ->assertJsonPath('student.student_number', $student->student_number)
            ->assertJsonPath('card.status', 'released')
            ->assertJsonPath('lifecycle.printed_at', $card->fresh()->printed_at->format('M j, Y g:i A'))
            ->assertJsonPath('lifecycle.released_at', null)
            ->assertJsonStructure(['history', 'discrepancies'])
            ->assertJsonFragment(['The card status says released, but no release transaction exists.']);

        $script = file_get_contents(resource_path('js/qr-scanner.js'));
        $this->assertStringContainsString('new QrScanner(', $script);
        $this->assertStringContainsString('QrScanner.scanImage', $script);
        $this->assertStringContainsString("preferredCamera: 'environment'", $script);
        $this->assertStringContainsString('stopCamera', $script);
        $this->assertStringNotContainsString('BarcodeDetector', $script);

        $this->actingAs($user)->get('/scanner')
            ->assertSee('Scan QR image')
            ->assertSee('accept="image/*"', false);
    }
    private function card(CardStatus $status, string $number = 'SYN-100'): array
    {
        [$student] = $this->student($number);

        return [$student, $this->makeCard($student, $status)];
    }

    private function makeCard(Student $student, CardStatus $status): IdCard
    {
        $enrollment = $student->enrollments()->latest('id')->first();

        return IdCard::create([
            'student_id' => $student->id,
            'academic_year_id' => $enrollment?->academic_year_id,
            'academic_term_id' => $enrollment?->academic_term_id,
            'card_token' => (string) Str::uuid(),
            'issuance_type' => 'initial',
            'status' => $status,
            'printed_at' => in_array($status, [CardStatus::Printed, CardStatus::ReadyForRelease, CardStatus::Released], true) ? now() : null,
        ]);
    }

    private function student(string $number): array
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026-2027']);
        $term = AcademicTerm::firstOrCreate(['academic_year_id' => $year->id, 'code' => 'first'], ['name' => 'First Semester', 'status' => 'active']);
        $program = Program::firstOrCreate(['code' => 'BSIT'], ['name' => 'Information Technology']);
        $student = Student::create(['student_number' => $number, 'first_name' => 'Synthetic', 'last_name' => 'Scanner']);
        $enrollment = $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year', 'section' => 'A']);

        return [$student, $enrollment];
    }

    private function userWith(array $slugs): User
    {
        $role = Role::create(['name' => 'Scanner Test', 'slug' => 'scanner-'.Str::random(8)]);
        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission);
        } $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
