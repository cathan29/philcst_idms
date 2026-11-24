<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelEnrollmentComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_student_creation_endpoints_are_not_available(): void
    {
        $user = $this->userWith(['students.manage', 'students.view']);

        $this->actingAs($user)->get('/students/create')->assertNotFound();
        $this->actingAs($user)->post('/students', [
            'student_number' => '00001234',
            'first_name' => 'Manual',
            'last_name' => 'Entry',
        ])->assertStatus(405);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_csv_template_uses_exact_panel_header_and_no_section_column(): void
    {
        $response = $this->actingAs($this->userWith(['students.view']))->get('/imports/students/template');

        $response->assertOk();
        $firstLine = strtok($response->getContent(), "\r\n");
        $this->assertSame(
            'student_number,first_name,middle_name,last_name,suffix,program,year_level,academic_year,semester',
            $firstLine,
        );
        $this->assertStringNotContainsString('section', $response->getContent());
    }

    public function test_registry_hides_legacy_section_and_defaults_to_active_term(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $active = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $historical = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'second_semester', 'name' => 'Second Semester', 'status' => 'closed']);
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $visible = Student::create(['student_number' => '00000001', 'first_name' => 'Active', 'last_name' => 'Student']);
        $hidden = Student::create(['student_number' => '00000002', 'first_name' => 'Historical', 'last_name' => 'Student']);
        $visible->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $active->id, 'semester' => $active->name, 'year_level' => '1st Year', 'section' => 'Legacy Block Z']);
        $hidden->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $historical->id, 'semester' => $historical->name, 'year_level' => '2nd Year', 'section' => 'Old Block']);

        $response = $this->actingAs($this->userWith(['students.view']))->get('/students');

        $response->assertOk()
            ->assertSee('Active Student')
            ->assertDontSee('Historical Student')
            ->assertDontSee('Legacy Block Z')
            ->assertDontSee('Section');
        $this->assertSame($active->id, $response->viewData('students')->first()->displayEnrollment->academic_term_id);
    }

    public function test_database_rejects_duplicate_enrollment_for_same_student_and_term(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $student = Student::create(['student_number' => '00000123', 'first_name' => 'Unique', 'last_name' => 'Enrollment']);
        $values = ['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year'];
        $student->enrollments()->create($values);

        $this->expectException(QueryException::class);
        $student->enrollments()->create([...$values, 'semester' => '1']);
    }

    private function userWith(array $slugs): User
    {
        $role = Role::create(['name' => 'Panel test role', 'slug' => 'panel-test-role-'.uniqid()]);
        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}