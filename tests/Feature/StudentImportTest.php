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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_routes_require_import_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/imports/students')->assertForbidden();
    }

    public function test_import_requires_term_context_and_csv(): void
    {
        $user = $this->userWith('imports.manage');
        $this->actingAs($user)->post('/imports/students/preview', [
            'csv' => UploadedFile::fake()->createWithContent('students.txt', 'not csv'),
        ])->assertSessionHasErrors(['academic_term_id', 'coverage', 'csv']);
    }

    public function test_preview_is_term_aware_and_does_not_write_students(): void
    {
        [$program, , $term] = $this->context();
        $user = $this->userWith('imports.manage');
        $file = $this->csv("00001,Ana,Reyes,{$program->code},1st Year,A");

        $this->upload($user, $file, $term)->assertOk()->assertSee('Map Columns')->assertSee('2026-2027');
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('student_enrollments', 0);
    }

    public function test_duplicate_rows_and_conflicting_terms_are_reported(): void
    {
        [$program, , $term] = $this->context();
        $user = $this->userWith('imports.manage');
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,last_name,program,year_level,academic_year,semester\n00001,Ana,Reyes,{$program->code},1st Year,2025-2026,Second Semester\n00001,Ana,Reyes,{$program->code},1st Year,2026-2027,First Semester\n");

        $this->upload($user, $file, $term)->assertOk();
        $this->validateMapping($user, true)->assertOk()->assertSee('academic_year conflicts')->assertSee('Duplicates');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_same_student_has_distinct_first_and_second_semester_enrollments(): void
    {
        [$program, $year, $first] = $this->context();
        $second = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'second_semester', 'name' => 'Second Semester', 'status' => 'active']);
        $user = $this->userWith('imports.manage');

        $this->import($user, $this->csv("00007,Mia,Lopez,{$program->code},1st Year,A"), $first);
        $this->uploadAndValidate($user, $this->csv("00007,Mia,Lopez,{$program->code},1st Year,B"), $second);
        $this->confirmAndCommit($user, 'insert_only');

        $this->assertSame(1, Student::where('student_number', '00007')->count());
        $student = Student::where('student_number', '00007')->firstOrFail();
        $this->assertSame(2, $student->enrollments()->count());
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id, 'academic_term_id' => $first->id, 'section' => 'A']);
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id, 'academic_term_id' => $second->id, 'section' => 'B']);
    }

    public function test_new_term_uses_csv_year_level_and_program_without_auto_increment(): void
    {
        [$program, $year, $first] = $this->context();
        $newProgram = Program::create(['code' => 'BSBA', 'name' => 'Business Administration']);
        $second = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'second_semester', 'name' => 'Second Semester', 'status' => 'active']);
        $user = $this->userWith('imports.manage');

        $this->import($user, $this->csv("00008,Leo,Santos,{$program->code},1st Year,A"), $first);
        $this->import($user, $this->csv("00008,Leo,Santos,{$newProgram->code},2nd Year,C"), $second);

        $student = Student::where('student_number', '00008')->firstOrFail();
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id, 'academic_term_id' => $second->id, 'program_id' => $newProgram->id, 'year_level' => '2nd Year', 'section' => 'C']);
    }

    public function test_repeat_import_is_idempotent_and_updates_only_term_enrollment(): void
    {
        [$program, , $term] = $this->context();
        $user = $this->userWith('imports.manage');
        $this->import($user, $this->csv("00009,Lina,Garcia,{$program->code},1st Year,A"), $term);
        $this->import($user, $this->csv("00009,Lina,Garcia,{$program->code},1st Year,A"), $term);
        $this->import($user, $this->csv("00009,Lina,Garcia,{$program->code},1st Year,B"), $term);

        $student = Student::where('student_number', '00009')->firstOrFail();
        $this->assertSame(1, $student->enrollments()->where('academic_term_id', $term->id)->count());
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id, 'academic_term_id' => $term->id, 'section' => 'B']);
        $this->assertDatabaseHas('import_batches', ['unchanged_rows' => 1, 'status' => 'completed']);
    }

    public function test_import_preserves_leading_zeroes_and_id_history(): void
    {
        [$program, , $term] = $this->context();
        $user = $this->userWith('imports.manage');
        $student = Student::create(['student_number' => '00001', 'first_name' => 'Old', 'last_name' => 'Name']);
        $card = IdCard::create(['student_id' => $student->id, 'card_token' => (string) Str::uuid(), 'status' => CardStatus::Printed, 'printed_at' => now()]);

        $this->import($user, $this->csv("00001,Updated,Name,{$program->code},1st Year,A\n00002,New,Student,{$program->code},1st Year,A"), $term);

        $this->assertDatabaseHas('students', ['student_number' => '00001', 'first_name' => 'Updated']);
        $this->assertDatabaseHas('students', ['student_number' => '00002']);
        $this->assertDatabaseHas('id_cards', ['id' => $card->id, 'student_id' => $student->id, 'status' => 'printed']);
        $this->assertSame(1, IdCard::count());
    }

    public function test_specific_program_coverage_supplies_missing_program_column(): void
    {
        [$program, , $term] = $this->context();
        $user = $this->userWith('imports.manage');
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,last_name,year_level,section\n00031,Mia,Lopez,1st Year,A\n");
        $this->upload($user, $file, $term, $program)->assertOk();
        $this->validateMapping($user, false)->assertOk();
        $this->confirmAndCommit($user);
        $student = Student::where('student_number', '00031')->firstOrFail();
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id, 'program_id' => $program->id, 'academic_term_id' => $term->id]);
    }

    public function test_files_larger_than_ten_megabytes_are_accepted_under_configured_limit(): void
    {
        [$program, , $term] = $this->context();
        $user = $this->userWith('imports.manage');
        $padding = str_repeat(' ', 11 * 1024 * 1024);
        $file = UploadedFile::fake()->createWithContent('large.csv', "student_number,first_name,last_name,program,year_level,section\n00050,Large,File,{$program->code},1st Year,A\n{$padding}");
        $this->upload($user, $file, $term)->assertOk()->assertSee('Map Columns');
    }

    public function test_confirmation_token_prevents_duplicate_submission(): void
    {
        [$program, , $term] = $this->context();
        $user = $this->userWith('imports.manage');
        $this->uploadAndValidate($user, $this->csv("00020,Test,Student,{$program->code},1st Year,A"), $term);
        $this->actingAs($user)->post('/imports/students/confirm', ['mode' => 'insert_only'])->assertOk();
        $token = session('student_import_token');
        $payload = ['mode' => 'insert_only', 'confirmation_token' => $token];
        $this->actingAs($user)->post('/imports/students/commit', $payload)->assertRedirect();
        $this->actingAs($user)->post('/imports/students/commit', $payload)->assertStatus(419);
        $this->assertSame(1, Student::where('student_number', '00020')->count());
    }

    private function context(): array
    {
        $program = Program::firstOrCreate(['code' => 'BSIT'], ['name' => 'Information Technology']);
        $year = AcademicYear::firstOrCreate(['name' => '2026-2027']);
        $term = AcademicTerm::firstOrCreate(['academic_year_id' => $year->id, 'code' => 'first_semester'], ['name' => 'First Semester', 'status' => 'active']);

        return [$program, $year, $term];
    }

    private function csv(string $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,last_name,program,year_level,section\n{$rows}\n");
    }

    private function upload(User $user, UploadedFile $file, AcademicTerm $term, ?Program $program = null)
    {
        return $this->actingAs($user)->post('/imports/students/preview', ['academic_term_id' => $term->id, 'coverage' => $program ? 'specific' : 'all', 'coverage_program_id' => $program?->id, 'csv' => $file]);
    }

    private function validateMapping(User $user, bool $withTermColumns = false)
    {
        $mapping = ['student_number' => 'student_number', 'first_name' => 'first_name', 'last_name' => 'last_name', 'program' => 'program', 'year_level' => 'year_level', 'section' => 'section'];
        if ($withTermColumns) {
            $mapping += ['academic_year' => 'academic_year', 'semester' => 'semester'];
        }

        return $this->actingAs($user)->post('/imports/students/validate', ['mapping' => $mapping]);
    }

    private function uploadAndValidate(User $user, UploadedFile $file, AcademicTerm $term): void
    {
        $this->upload($user, $file, $term)->assertOk();
        $this->validateMapping($user)->assertOk();
    }

    private function confirmAndCommit(User $user, string $mode = 'insert_or_update'): void
    {
        $this->actingAs($user)->post('/imports/students/confirm', ['mode' => $mode])->assertOk();
        $this->actingAs($user)->post('/imports/students/commit', ['mode' => $mode, 'confirmation_token' => session('student_import_token')])->assertRedirect();
    }

    private function import(User $user, UploadedFile $file, AcademicTerm $term): void
    {
        $this->uploadAndValidate($user, $file, $term);
        $this->confirmAndCommit($user);
    }

    private function userWith(string $permissionSlug): User
    {
        $permission = Permission::create(['name' => $permissionSlug, 'slug' => $permissionSlug]);
        $role = Role::create(['name' => 'Import test role', 'slug' => 'import-test-'.Str::random(8)]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
