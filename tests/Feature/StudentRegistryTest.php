<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_requires_server_side_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/students')->assertForbidden();
    }

    public function test_authorized_user_can_search_active_term_students_on_server(): void
    {
        [$term, $program, $year] = $this->termContext();
        $ana = Student::create(['student_number' => '00001', 'first_name' => 'Ana', 'last_name' => 'Reyes']);
        $ben = Student::create(['student_number' => '00002', 'first_name' => 'Ben', 'last_name' => 'Santos']);
        foreach ([$ana, $ben] as $student) {
            $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year']);
        }

        $this->actingAs($this->userWith('students.view'))->get('/students?search=00001')
            ->assertOk()->assertSee('Ana Reyes')->assertDontSee('Ben Santos');
    }

    public function test_empty_registry_shows_single_onboarding_action_set_and_no_filters(): void
    {
        $response = $this->actingAs($this->userWith('students.view'))->get('/students');

        $response->assertOk()
            ->assertSee('Import your first student records')
            ->assertSee('Student search and filters will be available after the first CSV import.')
            ->assertDontSee('id="registryFilters"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'Download Template'));
    }

    public function test_populated_registry_moves_csv_actions_to_header_and_shows_filters(): void
    {
        [$term, $program, $year] = $this->termContext();
        $student = Student::create(['student_number' => '00003', 'first_name' => 'Carla', 'last_name' => 'Mendoza']);
        $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year']);

        $response = $this->actingAs($this->userWith('students.view'))->get('/students');

        $response->assertOk()->assertDontSee('Import your first student records')->assertSee('id="registryFilters"', false)->assertSee('Active Students');
        $this->assertSame(1, substr_count($response->getContent(), 'Download CSV Template'));
    }

    public function test_registry_uses_global_active_term_and_ignores_local_term_override(): void
    {
        [$first, $program, $year] = $this->termContext();
        $second = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'second_semester', 'name' => 'Second Semester', 'status' => 'closed']);
        $student = Student::create(['student_number' => '00040', 'first_name' => 'Term', 'last_name' => 'Student']);
        $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $first->id, 'semester' => $first->name, 'year_level' => '1st Year', 'section' => 'Legacy A']);
        $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $second->id, 'semester' => $second->name, 'year_level' => '2nd Year', 'section' => 'Legacy B']);

        $response = $this->actingAs($this->userWith('students.view'))->get('/students?academic_term='.$second->id);
        $response->assertOk()->assertSee('Term Student')->assertSee('First Semester')->assertSee('1st Year')->assertDontSee('Legacy A')->assertDontSee('Second Semester');
        $this->assertSame($first->id, $response->viewData('students')->first()->displayEnrollment->academic_term_id);
    }

    public function test_manager_can_correct_existing_student_and_preserve_leading_zeroes(): void
    {
        [$term, $program, $year] = $this->termContext();
        $student = Student::create(['student_number' => '0000123', 'first_name' => 'Maria', 'last_name' => 'Original']);
        $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year']);
        $this->actingAs($this->userWith('students.manage'))->put('/students/'.$student->id, [
            'student_number' => ' 0000123 ', 'first_name' => ' Maria ', 'middle_name' => '', 'last_name' => ' Dela Cruz ', 'suffix' => '', 'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('students', ['student_number' => '0000123', 'first_name' => 'Maria', 'last_name' => 'Dela Cruz']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'student.updated', 'auditable_type' => Student::class, 'auditable_id' => $student->id]);
    }

    public function test_correction_rejects_duplicate_student_number(): void
    {
        [$term, $program, $year] = $this->termContext();
        $first = Student::create(['student_number' => '001', 'first_name' => 'First', 'last_name' => 'Student']);
        $other = Student::create(['student_number' => '002', 'first_name' => 'Other', 'last_name' => 'Student']);
        foreach ([$first, $other] as $student) {
            $student->enrollments()->create(['program_id' => $program->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'semester' => $term->name, 'year_level' => '1st Year']);
        }

        $this->actingAs($this->userWith('students.manage'))->put('/students/'.$other->id, [
            'student_number' => '001', 'first_name' => 'Other', 'last_name' => 'Student', 'status' => 'active',
        ])->assertSessionHasErrors('student_number');
        $this->assertSame('002', $other->fresh()->student_number);
    }

    private function termContext(): array
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $program = Program::create(['code' => 'BSIT', 'name' => 'Information Technology']);

        return [$term, $program, $year];
    }

    private function userWith(string $permissionSlug): User
    {
        $permission = Permission::create(['name' => $permissionSlug, 'slug' => $permissionSlug]);
        $role = Role::create(['name' => 'Test role', 'slug' => 'test-role-'.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}