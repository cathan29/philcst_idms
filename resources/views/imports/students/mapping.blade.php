@extends('layouts.app')
@section('title', 'Map Student Columns')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Map CSV Columns</h1>
        <p class="page-subtitle">Match CSV headings to official PhilCST student enrollment fields.</p>
    </div>
    <a class="btn secondary" href="{{ route('imports.students.create') }}">&larr; Choose another file</a>
</div>

@include('imports.students._steps', ['current' => 2])

<div class="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[#E2E6EF] bg-white px-5 py-3 text-sm">
    <div>
        <span class="font-semibold text-[#172033]">Selected term:</span>
        <span class="ml-1.5 text-[#475569] num">{{ $preview['term']['label'] }} &middot; {{ empty($preview['context']['coverage_program_id']) ? 'All programs' : 'Specific program' }}</span>
    </div>
    <div class="text-xs font-medium text-[#64748B] num">
        {{ $preview['original_filename'] }} &middot; {{ number_format($preview['file_size'] / 1024, 1) }} KB &middot; {{ count($preview['headers']) }} columns
    </div>
</div>

<section class="surface max-w-4xl overflow-hidden">
    <form method="POST" action="{{ route('imports.students.validate') }}" class="mapping-form">
        @csrf

        @if($errors->any())
            <div class="border-b border-[#FECACA] bg-[#FEF2F2] px-6 py-3.5 text-sm text-[#991B1B]" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="p-6">
            <h2 class="text-base font-semibold text-[#172033]">Column Mapping</h2>
            <p class="mt-0.5 text-sm text-[#64748B]">Required fields are marked. Each CSV column may only be mapped once. Map separate first and last name columns.</p>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    'student_number' => 'Student Number',
                    'first_name' => 'First Name',
                    'middle_name' => 'Middle Name',
                    'last_name' => 'Last Name',
                    'suffix' => 'Suffix',
                    'status' => 'Student Status',
                    'program' => 'Program',
                    'year_level' => 'Year Level',
                    'academic_year' => 'Academic Year',
                    'semester' => 'Semester',
                ] as $field => $label)
                    <label class="block">
                        <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">
                            {{ $label }}
                            @if(in_array($field, ['student_number', 'first_name', 'last_name']))
                                <span class="text-[#B91C1C]">*</span>
                            @endif
                        </span>
                        <select name="mapping[{{ $field }}]" @required(in_array($field, ['student_number', 'first_name', 'last_name']))>
                            <option value="">Not mapped</option>
                            @foreach($preview['headers'] as $index => $header)
                                <option value="{{ $header }}" @selected(old("mapping.$field", isset($preview['mapping'][$field]) ? $preview['headers'][$preview['mapping'][$field]] : '') === $header)>{{ $header }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
        </div>

        <footer class="flex items-center justify-end gap-2.5 border-t border-[#E2E6EF] bg-[#F8F9FC] px-6 py-4">
            <a class="btn secondary" href="{{ route('imports.students.create') }}">Back</a>
            <button class="btn primary" type="submit" id="validateMapping">Validate Records</button>
        </footer>
    </form>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('.mapping-form');
    form?.addEventListener('submit', e => {
        const selected = [...form.querySelectorAll('select')].map(s => s.value).filter(Boolean);
        if (new Set(selected).size !== selected.length) {
            e.preventDefault();
            alert('Each CSV column can only be mapped once.');
            return;
        }
        const button = document.getElementById('validateMapping');
        button.disabled = true;
        button.textContent = 'Validating...';
    });
})();
</script>
@endpush
