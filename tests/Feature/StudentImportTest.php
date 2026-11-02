<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
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

    public function test_upload_requires_a_csv_and_rejects_empty_files(): void
    {
        $user = $this->userWith('imports.manage');

        $this->actingAs($user)->post('/imports/students/preview', [
            'csv' => UploadedFile::fake()->createWithContent('students.txt', 'not csv'),
        ])->assertSessionHasErrors('csv');

        $this->actingAs($user)->post('/imports/students/preview', [
            'csv' => UploadedFile::fake()->createWithContent('students.csv', ''),
        ])->assertSessionHasErrors('csv');
    }

    public function test_upload_opens_mapping_without_writing_students(): void
    {
        $user = $this->userWith('imports.manage');
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,last_name\n00001,Ana,Reyes\n");

        $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file])
            ->assertOk()
            ->assertSee('Map Columns')
            ->assertSee('student_number');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_custom_headers_are_mapped_then_validated_without_writing(): void
    {
        $user = $this->userWith('imports.manage');
        $file = UploadedFile::fake()->createWithContent('custom.csv', "ID Number,Given,Family,State\n00009,Lina,Garcia,active\n");

        $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file])->assertOk();
        $this->actingAs($user)->post('/imports/students/validate', ['mapping' => [
            'student_number' => 'id number', 'first_name' => 'given', 'last_name' => 'family', 'status' => 'state',
        ]])->assertOk()->assertSee('Review &amp; Validate', false)->assertSee('00009');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_duplicate_mapping_and_duplicate_rows_are_rejected_or_classified(): void
    {
        $user = $this->userWith('imports.manage');
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,last_name\n00001,Ana,Reyes\n00001,Ana,Reyes\n");
        $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file])->assertOk();

        $this->actingAs($user)->post('/imports/students/validate', ['mapping' => [
            'student_number' => 'student_number', 'first_name' => 'first_name', 'last_name' => 'first_name',
        ]])->assertSessionHasErrors('mapping');

        $this->actingAs($user)->post('/imports/students/validate', ['mapping' => [
            'student_number' => 'student_number', 'first_name' => 'first_name', 'last_name' => 'last_name',
        ]])->assertOk()->assertSee('Duplicates')->assertSee('1');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_confirmed_import_preserves_leading_zeroes_and_card_history(): void
    {
        $user = $this->userWith('imports.manage');
        $student = Student::create(['student_number' => '00001', 'first_name' => 'Old', 'last_name' => 'Name']);
        IdCard::create(['student_id' => $student->id, 'card_token' => (string) Str::uuid(), 'status' => CardStatus::Printed]);
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,last_name,status\n00001,Updated,Name,active\n00002,New,Student,active\n");

        $this->uploadAndValidate($user, $file);
        $confirm = $this->actingAs($user)->post('/imports/students/confirm', ['mode' => 'insert_or_update'])->assertOk();
        $token = session('student_import_token');
        $this->actingAs($user)->post('/imports/students/commit', [
            'mode' => 'insert_or_update', 'confirmation_token' => $token,
        ])->assertRedirect();

        $this->assertDatabaseHas('students', ['student_number' => '00001', 'first_name' => 'Updated']);
        $this->assertDatabaseHas('students', ['student_number' => '00002', 'first_name' => 'New']);
        $this->assertDatabaseHas('id_cards', ['student_id' => $student->id, 'status' => 'printed']);
        $this->assertDatabaseHas('import_batches', ['imported_rows' => 1, 'updated_rows' => 1, 'status' => 'completed']);
    }

    public function test_invalid_year_level_is_reported_during_validation(): void
    {
        $user = $this->userWith('imports.manage');
        Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        AcademicYear::create(['name' => '2026-2027']);
        $file = UploadedFile::fake()->createWithContent('students.csv', "Student Number,First Name,Last Name,Program,Year Level,Academic Year\n00030,Alex,Cruz,BSIT,Level X,2026-2027\n");

        $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file])->assertOk();
        $this->actingAs($user)->post('/imports/students/validate', ['mapping' => [
            'student_number' => 'student number', 'first_name' => 'first name', 'last_name' => 'last name',
            'program' => 'program', 'year_level' => 'year level', 'academic_year' => 'academic year',
        ]])->assertOk()->assertSee('year_level is invalid');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_valid_enrollment_columns_are_imported_against_authorized_references(): void
    {
        $user = $this->userWith('imports.manage');
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $year = AcademicYear::create(['name' => '2026-2027']);
        $file = UploadedFile::fake()->createWithContent('students.csv', "Student Number,First Name,Last Name,Program,Year Level,Section,Academic Year,Semester\n00031,Mia,Lopez,BSIT,1st Year,A,2026-2027,First\n");
        $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file])->assertOk();
        $this->actingAs($user)->post('/imports/students/validate', ['mapping' => [
            'student_number' => 'student number', 'first_name' => 'first name', 'last_name' => 'last name',
            'program' => 'program', 'year_level' => 'year level', 'section' => 'section',
            'academic_year' => 'academic year', 'semester' => 'semester',
        ]])->assertOk();
        $confirm = $this->actingAs($user)->post('/imports/students/confirm', ['mode' => 'insert_only']);
        $this->actingAs($user)->post('/imports/students/commit', [
            'mode' => 'insert_only', 'confirmation_token' => session('student_import_token'),
        ])->assertRedirect();

        $student = Student::where('student_number', '00031')->firstOrFail();
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id, 'program_id' => $program->id, 'academic_year_id' => $year->id,
            'year_level' => '1st Year', 'section' => 'A', 'semester' => 'First',
        ]);
    }

    public function test_confirmation_token_prevents_duplicate_submission(): void
    {
        $user = $this->userWith('imports.manage');
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,last_name\n00020,Test,Student\n");
        $this->uploadAndValidate($user, $file);
        $confirm = $this->actingAs($user)->post('/imports/students/confirm', ['mode' => 'insert_only']);
        $token = session('student_import_token');
        $payload = ['mode' => 'insert_only', 'confirmation_token' => $token];

        $this->actingAs($user)->post('/imports/students/commit', $payload)->assertRedirect();
        $this->actingAs($user)->post('/imports/students/commit', $payload)->assertStatus(419);
        $this->assertSame(1, Student::where('student_number', '00020')->count());
        $this->assertDatabaseCount('import_batches', 1);
    }

    private function uploadAndValidate(User $user, UploadedFile $file): void
    {
        $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file])->assertOk();
        $this->actingAs($user)->post('/imports/students/validate', ['mapping' => [
            'student_number' => 'student_number', 'first_name' => 'first_name', 'last_name' => 'last_name', 'status' => 'status',
        ]])->assertOk();
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
