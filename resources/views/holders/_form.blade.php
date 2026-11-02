@if($errors->any())<div class="alert danger"><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="form-grid">
    <label>ID number<input name="id_number" value="{{ old('id_number', $holder->id_number ?? '') }}" placeholder="STU-2026-001" required></label>
    <label>Category<select name="holder_type" required>@foreach(['student','employee','visitor'] as $type)<option value="{{ $type }}" @selected(old('holder_type', $holder->holder_type ?? 'student') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></label>
    <label>First name<input name="first_name" value="{{ old('first_name', $holder->first_name ?? '') }}" required></label>
    <label>Last name<input name="last_name" value="{{ old('last_name', $holder->last_name ?? '') }}" required></label>
    <label>Department / Course<input name="department" value="{{ old('department', $holder->department ?? '') }}"></label>
    <label>Status<select name="status" required>@foreach(['active','inactive'] as $status)<option value="{{ $status }}" @selected(old('status', $holder->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $holder->email ?? '') }}"></label>
    <label>Phone<input name="phone" value="{{ old('phone', $holder->phone ?? '') }}"></label>
</div>
<div class="form-actions"><a class="btn secondary" href="{{ route('holders.index') }}">Cancel</a><button class="btn primary" type="submit">{{ $submitLabel }}</button></div>
