<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::with('roles')->orderBy('name')->paginate(25),
            'roles' => Role::with('permissions')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->roles()->sync($data['role_ids']);
            $this->audit($request, 'user.created', $user, null, [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => true,
                'role_ids' => array_map('intval', $data['role_ids']),
            ]);
        }, 3);

        return redirect()->route('users.index')->with('success', 'Staff account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'is_active' => ['required', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'password' => ['nullable', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if ($request->user()->is($user) && ! $data['is_active']) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own signed-in account.']);
        }

        $requestedRoles = collect($data['role_ids'])->map(fn ($id) => (int) $id)->sort()->values();
        $currentRoles = $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->sort()->values();
        if ($request->user()->is($user) && $requestedRoles->all() !== $currentRoles->all()) {
            return back()->withErrors(['role_ids' => 'You cannot change your own roles while signed in.']);
        }

        DB::transaction(function () use ($data, $request, $user): void {
            $old = [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'role_ids' => $user->roles()->pluck('roles.id')->all(),
            ];
            $values = [
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => (bool) $data['is_active'],
                'deactivated_at' => $data['is_active'] ? null : now(),
            ];
            if (filled($data['password'] ?? null)) {
                $values['password'] = $data['password'];
            }
            $user->forceFill($values)->save();
            $user->roles()->sync($data['role_ids']);
            $this->audit($request, 'user.updated', $user, $old, [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'role_ids' => array_map('intval', $data['role_ids']),
                'password_changed' => filled($data['password'] ?? null),
            ]);
        }, 3);

        return redirect()->route('users.index')->with('success', 'Staff account updated.');
    }

    private function audit(Request $request, string $event, User $user, ?array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'occurred_at' => now(),
        ]);
    }
}