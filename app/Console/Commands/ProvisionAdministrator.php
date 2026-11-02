<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProvisionAdministrator extends Command
{
    protected $signature = 'idms:provision-admin {--name=} {--email=}';

    protected $description = 'Interactively provision the first PhilCST IDMS administrator';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Institutional email');
        $password = $this->secret('Password (minimum 12 characters)');

        $validator = validator(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password) {
            $permission = Permission::firstOrCreate(['slug' => '*'], ['name' => 'All permissions']);
            $role = Role::firstOrCreate(['slug' => 'super-administrator'], ['name' => 'Super Administrator']);
            $role->permissions()->syncWithoutDetaching($permission);
            $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'is_active' => true]);
            $user->roles()->attach($role);
        });

        $this->components->info('Administrator provisioned. No password was written to logs.');

        return self::SUCCESS;
    }
}
