@extends('layouts.app')
@section('title', 'Users')
@section('content')
<div class="mb-6">
    <h1 class="page-title">User Management</h1>
    <p class="page-subtitle">Create staff accounts, assign approved roles, and control account access.</p>
</div>

@if($errors->any())
    <div class="alert-banner error" role="alert">{{ $errors->first() }}</div>
@endif

<section class="surface mb-6 p-6">
    <h2 class="text-base font-semibold text-[#172033]">Create Staff Account</h2>
    <p class="mt-0.5 text-sm text-[#64748B]">Use an institutional email and assign only the roles required for the staff member's duties.</p>

    <form method="POST" action="{{ route('users.store') }}" class="mt-5 space-y-5">
        @csrf
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Full name</span>
                <input name="name" type="text" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Password</span>
                <input type="password" name="password" required minlength="12" autocomplete="new-password">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Confirm password</span>
                <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
            </label>
        </div>

        <fieldset class="border-t border-[#E2E6EF] pt-4">
            <legend class="mb-2.5 text-[13.5px] font-semibold text-[#334155]">Assign Roles</legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($roles as $role)
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-[#E2E6EF] bg-[#F8F9FC] p-3 text-sm hover:border-[#CBD3E1]">
                        <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(collect(old('role_ids', []))->contains($role->id)) class="mt-0.5">
                        <span>
                            <strong class="block font-semibold text-[#172033]">{{ $role->name }}</strong>
                            <span class="mt-0.5 block text-xs text-[#64748B]">{{ $role->permissions->pluck('name')->implode(', ') ?: 'No permissions assigned' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div>
            <button class="btn primary" type="submit">Create account</button>
        </div>
    </form>
</section>

<section class="surface overflow-hidden">
    <div class="flex items-center justify-between border-b border-[#E2E6EF] px-5 py-4">
        <h2 class="text-base font-semibold text-[#172033]">Staff Accounts</h2>
        <span class="text-xs font-medium text-[#64748B] num">{{ number_format($users->total()) }} registered {{ $users->total() === 1 ? 'account' : 'accounts' }}</span>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Roles</th>
                    <th>Status</th>
                    <th>Account Controls</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $staff)
                    <tr>
                        <td>
                            <span class="font-semibold text-[#172033]">{{ $staff->name }}</span>
                            <span class="block text-xs text-[#64748B]">{{ $staff->email }}</span>
                        </td>
                        <td class="text-sm text-[#475569]">{{ $staff->roles->pluck('name')->implode(', ') ?: 'No role' }}</td>
                        <td>
                            <span class="badge {{ $staff->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $staff->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <details>
                                <summary class="btn secondary btn-sm cursor-pointer list-none w-fit">Manage</summary>
                                <form method="POST" action="{{ route('users.update', $staff) }}" class="mt-3 max-w-md space-y-3 rounded-lg border border-[#E2E6EF] bg-[#F8F9FC] p-4">
                                    @csrf
                                    @method('PUT')
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-medium text-[#475569]">Name</span>
                                        <input name="name" type="text" value="{{ $staff->name }}" required>
                                    </label>
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-medium text-[#475569]">Email</span>
                                        <input type="email" name="email" value="{{ $staff->email }}" required>
                                    </label>
                                    <fieldset>
                                        <legend class="mb-1.5 text-xs font-medium text-[#475569]">Roles</legend>
                                        <div class="space-y-1.5">
                                            @foreach($roles as $role)
                                                <label class="flex items-center gap-2 text-xs text-[#334155]">
                                                    <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($staff->roles->contains($role)) @disabled(auth()->user()->is($staff))>
                                                    <span>{{ $role->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if(auth()->user()->is($staff))
                                            @foreach($staff->roles as $role)
                                                <input type="hidden" name="role_ids[]" value="{{ $role->id }}">
                                            @endforeach
                                        @endif
                                    </fieldset>
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-medium text-[#475569]">Account status</span>
                                        <select name="is_active" @disabled(auth()->user()->is($staff))>
                                            <option value="1" @selected($staff->is_active)>Active</option>
                                            <option value="0" @selected(!$staff->is_active)>Inactive</option>
                                        </select>
                                        @if(auth()->user()->is($staff))
                                            <input type="hidden" name="is_active" value="1">
                                        @endif
                                    </label>
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-medium text-[#475569]">New password (optional)</span>
                                        <input type="password" name="password" minlength="12" autocomplete="new-password">
                                    </label>
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-medium text-[#475569]">Confirm new password</span>
                                        <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password">
                                    </label>
                                    <button class="btn primary btn-sm" type="submit">Save account</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-10 text-center text-sm text-[#64748B]">No staff accounts are configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="pagination-bar">
            {{ $users->links() }}
        </div>
    @endif
</section>
@endsection
