<?php

namespace Tests\Feature;

use App\Enums\SemesterCode;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ActiveAcademicTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveAcademicTermTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_is_exclusive_cached_and_invalidated(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $first = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $second = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'second_semester', 'name' => 'Second Semester', 'status' => 'closed']);
        $terms = app(ActiveAcademicTerm::class);

        $this->assertTrue($terms->current()->is($first));
        $terms->activate($second);

        $this->assertSame('closed', $first->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);
        $this->assertTrue($terms->current()->is($second));
        $this->assertSame(1, AcademicTerm::where('status', 'active')->count());
    }

    public function test_zero_semester_can_be_created_and_activated_from_settings(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);

        $this->actingAs($this->userWith('settings.manage'))->post('/academic-terms', [
            'academic_year_id' => $year->id,
            'semester' => '0',
            'starts_on' => '2027-05-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_terms', [
            'academic_year_id' => $year->id,
            'code' => SemesterCode::Summer->databaseCode(),
            'name' => SemesterCode::Summer->label(),
            'status' => 'active',
        ]);
        $this->assertSame(1, AcademicTerm::where('status', 'active')->count());
    }

    public function test_closing_current_term_clears_cached_context(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'code' => 'first_semester', 'name' => 'First Semester', 'status' => 'active']);
        $terms = app(ActiveAcademicTerm::class);
        $this->assertTrue($terms->current()->is($term));

        $this->actingAs($this->userWith('settings.manage'))->put('/academic-terms/'.$term->id, [
            'name' => 'First Semester',
            'starts_on' => null,
            'ends_on' => null,
            'status' => 'closed',
        ])->assertRedirect();

        $this->assertNull($terms->current());
    }

    private function userWith(string $slug): User
    {
        $permission = Permission::create(['name' => $slug, 'slug' => $slug]);
        $role = Role::create(['name' => 'Term manager', 'slug' => 'term-manager']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}