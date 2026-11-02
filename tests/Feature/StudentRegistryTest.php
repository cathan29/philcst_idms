<?php

namespace Tests\Feature;

use App\Models\Permission;
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

    public function test_authorized_user_can_search_students_on_server(): void
    {
        $user = $this->userWith('students.view');
        Student::create(['student_number' => '00001', 'first_name' => 'Ana', 'last_name' => 'Reyes']);
        Student::create(['student_number' => '00002', 'first_name' => 'Ben', 'last_name' => 'Santos']);

        $this->actingAs($user)->get('/students?search=00001')
            ->assertOk()
            ->assertSee('Ana Reyes')
            ->assertDontSee('Ben Santos');
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
        Student::create(['student_number' => '00003', 'first_name' => 'Carla', 'last_name' => 'Mendoza']);

        $response = $this->actingAs($this->userWith('students.view'))->get('/students');

        $response->assertOk()
            ->assertDontSee('Import your first student records')
            ->assertSee('id="registryFilters"', false)
            ->assertSee('Active Students');
        $this->assertSame(1, substr_count($response->getContent(), 'Download CSV Template'));
    }

    public function test_manager_can_create_student_and_leading_zeroes_are_preserved(): void
    {
        $user = $this->userWith('students.manage');

        $this->actingAs($user)->post('/students', [
            'student_number' => ' 0000123 ',
            'first_name' => ' Maria ',
            'middle_name' => '',
            'last_name' => ' Dela Cruz ',
            'suffix' => '',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('students', [
            'student_number' => '0000123',
            'first_name' => 'Maria',
            'last_name' => 'Dela Cruz',
            'record_source' => 'manual',
        ]);
    }

    public function test_duplicate_student_number_is_rejected(): void
    {
        $user = $this->userWith('students.manage');
        Student::create(['student_number' => '001', 'first_name' => 'First', 'last_name' => 'Student']);

        $this->actingAs($user)->post('/students', [
            'student_number' => '001', 'first_name' => 'Other', 'last_name' => 'Student', 'status' => 'active',
        ])->assertSessionHasErrors('student_number');

        $this->assertDatabaseCount('students', 1);
    }

    private function userWith(string $permissionSlug): User
    {
        $permission = Permission::create(['name' => $permissionSlug, 'slug' => $permissionSlug]);
        $role = Role::create(['name' => 'Test role', 'slug' => 'test-role']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
