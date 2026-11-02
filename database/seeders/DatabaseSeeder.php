<?php

namespace Database\Seeders;

use App\Models\IdHolder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = collect([
            'students.view', 'students.manage', 'imports.manage', 'cards.view', 'cards.manage',
            'cards.print', 'cards.release', 'reports.view', 'settings.manage', 'users.manage', 'audit.view',
        ])->mapWithKeys(fn (string $slug) => [$slug => Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->replace('.', ' ')->title()],
        )]);

        $roles = [
            'administrator' => ['students.view', 'students.manage', 'imports.manage', 'cards.view', 'cards.manage', 'reports.view', 'settings.manage'],
            'id-production-staff' => ['students.view', 'cards.view', 'cards.print'],
            'releasing-staff' => ['students.view', 'cards.view', 'cards.release'],
            'viewer-auditor' => ['students.view', 'cards.view', 'reports.view', 'audit.view'],
        ];
        foreach ($roles as $slug => $grants) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => str($slug)->replace('-', ' ')->title()]);
            $role->permissions()->sync(collect($grants)->map(fn ($grant) => $permissions[$grant]->id));
        }

        // Keep a repeatable local administrator available after migrate:fresh --seed.
        // Override these values in .env when a different local login is preferred.
        $allPermissions = Permission::firstOrCreate(['slug' => '*'], ['name' => 'All permissions']);
        $superAdmin = Role::firstOrCreate(['slug' => 'super-administrator'], ['name' => 'Super Administrator']);
        $superAdmin->permissions()->syncWithoutDetaching([$allPermissions->id]);

        $adminEmail = env('IDMS_SEED_ADMIN_EMAIL', 'admin@philcst.local');
        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => env('IDMS_SEED_ADMIN_NAME', 'PhilCST Administrator'),
                'password' => Hash::make(env('IDMS_SEED_ADMIN_PASSWORD', 'Admin@12345PhilCST!')),
                'is_active' => true,
            ],
        );
        $admin->forceFill(['is_active' => true])->save();
        $admin->roles()->syncWithoutDetaching([$superAdmin->id]);

        $holders = [
            ['id_number' => 'STU-2026-001', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'holder_type' => 'student', 'department' => 'Information Technology', 'email' => 'juan@example.com', 'status' => 'active'],
            ['id_number' => 'STU-2026-002', 'first_name' => 'Maria', 'last_name' => 'Santos', 'holder_type' => 'student', 'department' => 'Business Administration', 'email' => 'maria@example.com', 'status' => 'active'],
            ['id_number' => 'EMP-2026-001', 'first_name' => 'Roberto', 'last_name' => 'Reyes', 'holder_type' => 'employee', 'department' => 'Administration', 'email' => 'roberto@example.com', 'status' => 'active'],
        ];

        foreach ($holders as $holder) {
            IdHolder::updateOrCreate(['id_number' => $holder['id_number']], $holder);
        }

        $this->command?->info("Local administrator ready: {$adminEmail}");
    }
}
