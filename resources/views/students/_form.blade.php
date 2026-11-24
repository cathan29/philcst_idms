@if($errors->any())
    <div class="alert-banner error" role="alert">
        <div>
            <p class="font-semibold">Please correct the highlighted fields.</p>
            <ul class="mt-1 list-disc pl-4 text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <label class="block">
        <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Student number</span>
        <input name="student_number" type="text" value="{{ old('student_number', $student->student_number ?? '') }}" maxlength="64" required class="mono">
        <span class="mt-1 block text-xs text-[#64748B]">Leading zeroes are preserved.</span>
    </label>
    <label class="block">
        <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Status</span>
        <select name="status" required>
            @foreach(['active','inactive','graduated','withdrawn'] as $status)
                <option value="{{ $status }}" @selected(old('status', $student->status ?? 'active') === $status)>{{ str($status)->title() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">First name</span>
        <input name="first_name" type="text" value="{{ old('first_name', $student->first_name ?? '') }}" maxlength="100" required>
    </label>
    <label class="block">
        <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Middle name (optional)</span>
        <input name="middle_name" type="text" value="{{ old('middle_name', $student->middle_name ?? '') }}" maxlength="100">
    </label>
    <label class="block">
        <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Last name</span>
        <input name="last_name" type="text" value="{{ old('last_name', $student->last_name ?? '') }}" maxlength="100" required>
    </label>
    <label class="block">
        <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Suffix (optional)</span>
        <input name="suffix" type="text" value="{{ old('suffix', $student->suffix ?? '') }}" maxlength="30">
    </label>
</div>

<div class="mt-6 flex items-center justify-end gap-2.5 border-t border-[#E2E6EF] pt-4">
    <a class="btn secondary" href="{{ isset($student) ? route('students.show', $student) : route('students.index') }}">Cancel</a>
    <button class="btn primary" type="submit">{{ $submitLabel }}</button>
</div>
