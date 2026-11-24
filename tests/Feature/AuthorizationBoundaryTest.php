<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_require_reports_permission_not_general_card_access(): void
    {
        $this->actingAs($this->userWith(['cards.view']))->get('/reports')->assertForbidden();
        $this->actingAs($this->userWith(['reports.view']))->get('/reports')->assertOk();
        $this->actingAs($this->userWith(['reports.view']))->get('/reports/export')->assertOk();
    }

    public function test_legacy_directory_and_full_logs_are_restricted(): void
    {
        $viewer = $this->userWith(['cards.view']);
        $this->actingAs($viewer)->get('/holders')->assertForbidden();
        $this->actingAs($viewer)->get('/logs')->assertForbidden();

        $this->actingAs($this->userWith(['users.manage']))->get('/holders')->assertOk();
        $this->actingAs($this->userWith(['audit.view']))->get('/logs')->assertOk();
    }

    public function test_user_management_requires_dedicated_permission(): void
    {
        $this->actingAs($this->userWith(['cards.view']))->get('/users')->assertForbidden();
        $this->actingAs($this->userWith(['users.manage']))->get('/users')->assertOk()->assertSee('User Management');
    }

    public function test_authorized_manager_can_create_and_update_staff_accounts(): void
    {
        $manager = $this->userWith(['users.manage']);
        $staffRole = Role::create(['name' => 'Verification Staff', 'slug' => 'verification-staff']);

        $this->actingAs($manager)->post('/users', [
            'name' => 'Local Verification Staff',
            'email' => 'verification@philcst.local',
            'password' => 'Secure@12345Staff',
            'password_confirmation' => 'Secure@12345Staff',
            'role_ids' => [$staffRole->id],
        ])->assertRedirect('/users');

        $staff = User::where('email', 'verification@philcst.local')->firstOrFail();
        $this->assertTrue(password_verify('Secure@12345Staff', $staff->password));
        $this->assertTrue($staff->roles()->whereKey($staffRole->id)->exists());

        $this->actingAs($manager)->put("/users/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'is_active' => '0',
            'role_ids' => [$staffRole->id],
        ])->assertRedirect('/users');
        $this->assertFalse((bool) $staff->fresh()->is_active);
    }

    public function test_manager_cannot_deactivate_own_account(): void
    {
        $manager = $this->userWith(['users.manage']);

        $this->actingAs($manager)->put("/users/{$manager->id}", [
            'name' => $manager->name,
            'email' => $manager->email,
            'is_active' => '0',
            'role_ids' => $manager->roles()->pluck('roles.id')->all(),
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue((bool) $manager->fresh()->is_active);
    }
    private function userWith(array $slugs): User
    {
        $role = Role::create(['name' => 'Boundary Test', 'slug' => 'boundary-'.Str::random(8)]);
        foreach ($slugs as $slug) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}