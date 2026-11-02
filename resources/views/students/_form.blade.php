@if($errors->any())<div class="alert error"><strong>Please correct the highlighted fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="form-grid">
    <label>Student number<input name="student_number" value="{{ old('student_number', $student->student_number ?? '') }}" maxlength="64" required><small>Leading zeroes are preserved.</small></label>
    <label>First name<input name="first_name" value="{{ old('first_name', $student->first_name ?? '') }}" maxlength="100" required></label>
    <label>Middle name (optional)<input name="middle_name" value="{{ old('middle_name', $student->middle_name ?? '') }}" maxlength="100"></label>
    <label>Last name<input name="last_name" value="{{ old('last_name', $student->last_name ?? '') }}" maxlength="100" required></label>
    <label>Suffix (optional)<input name="suffix" value="{{ old('suffix', $student->suffix ?? '') }}" maxlength="30"></label>
    <label>Status<select name="status" required>@foreach(['active','inactive','graduated','withdrawn'] as $status)<option value="{{ $status }}" @selected(old('status', $student->status ?? 'active') === $status)>{{ str($status)->title() }}</option>@endforeach</select></label>
</div>
<div class="form-actions"><a class="btn" href="{{ isset($student) ? route('students.show', $student) : route('students.index') }}">Cancel</a><button class="btn primary">{{ $submitLabel }}</button></div>
