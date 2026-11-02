<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\IdCard;
use App\Models\Permission;
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

    public function test_valid_csv_is_previewed_without_writing_students(): void
    {
        $user = $this->userWith('imports.manage');
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,middle_name,last_name,status\n00001,Ana,,Reyes,active\n00002,Ben,,Santos,active\n");

        $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file])
            ->assertOk()
            ->assertSee('New students')
            ->assertSee('00001');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_duplicate_and_invalid_rows_are_classified_and_not_committed(): void
    {
        $user = $this->userWith('imports.manage');
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,last_name,status\n00001,Ana,Reyes,active\n00001,Duplicate,Reyes,active\n00002,,Santos,active\n");

        $response = $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file]);

        $response->assertOk()->assertSee('Duplicate rows')->assertSee('Invalid rows');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_confirmed_import_inserts_and_updates_without_resetting_card_status(): void
    {
        $user = $this->userWith('imports.manage');
        $student = Student::create(['student_number' => '00001', 'first_name' => 'Old', 'last_name' => 'Name']);
        IdCard::create(['student_id' => $student->id, 'card_token' => (string) Str::uuid(), 'status' => CardStatus::Printed]);
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_number,first_name,last_name,status\n00001,Updated,Name,active\n00002,New,Student,active\n");

        $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file])->assertOk();
        $this->actingAs($user)->post('/imports/students/commit', ['mode' => 'insert_or_update'])->assertRedirect('/students');

        $this->assertDatabaseHas('students', ['student_number' => '00001', 'first_name' => 'Updated']);
        $this->assertDatabaseHas('students', ['student_number' => '00002', 'first_name' => 'New']);
        $this->assertDatabaseHas('id_cards', ['student_id' => $student->id, 'status' => 'printed']);
        $this->assertDatabaseHas('import_batches', ['imported_rows' => 1, 'updated_rows' => 1, 'status' => 'completed']);
    }

    public function test_custom_headers_can_be_mapped_at_confirmation(): void
    {
        $user = $this->userWith('imports.manage');
        $file = UploadedFile::fake()->createWithContent('custom.csv', "id_number,given,family,state\n00009,Lina,Garcia,active\n");

        $this->actingAs($user)->post('/imports/students/preview', ['csv' => $file])->assertOk();
        $this->actingAs($user)->post('/imports/students/commit', [
            'mode' => 'insert_only',
            'mapping' => ['student_number' => 'id_number', 'first_name' => 'given', 'last_name' => 'family', 'status' => 'state'],
        ])->assertRedirect('/students');

        $this->assertDatabaseHas('students', ['student_number' => '00009', 'first_name' => 'Lina', 'last_name' => 'Garcia']);
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
