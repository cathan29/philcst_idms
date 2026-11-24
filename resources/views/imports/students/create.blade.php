@extends('layouts.app')
@section('title', 'Import CSV')
@section('content')
@php($activeTerms = $terms)

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Import Student Enrollments</h1>
        <p class="page-subtitle">Set the active academic term context, then upload an official 9-column enrollment CSV.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2.5">
        <a class="btn secondary" href="{{ route('imports.students.template') }}">Download Template</a>
        <a class="btn text" href="{{ route('students.index') }}">Back to Student Records</a>
    </div>
</div>

@include('imports.students._steps', ['current' => 1])

<section class="surface max-w-4xl overflow-hidden">
    <form action="{{ route('imports.students.preview') }}" method="POST" enctype="multipart/form-data" id="csvUploadForm">
        @csrf

        @if($errors->any())
            <div class="border-b border-[#FECACA] bg-[#FEF2F2] px-6 py-4 text-sm text-[#991B1B]" role="alert">
                <p class="font-semibold">Check the information below.</p>
                <ul class="mt-1 list-disc pl-5 text-xs">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="border-b border-[#E2E6EF] p-6">
            <h2 id="academic-context-title" class="text-base font-semibold text-[#172033]">Academic Context</h2>
            <p class="mt-0.5 text-sm text-[#64748B]">Choose the target term and program coverage for every approved row.</p>

            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Active academic year and semester <span class="text-[#B91C1C]">*</span></span>
                    <select name="academic_term_id" id="academicTerm" required aria-describedby="academicTermHelp">
                        <option value="">Select active term</option>
                        @foreach($activeTerms as $term)
                            <option value="{{ $term->id }}" @selected(old('academic_term_id', $activeTerms->count() === 1 ? $term->id : null) == $term->id)>AY {{ $term->academicYear->name }}  -  {{ $term->name }}</option>
                        @endforeach
                    </select>
                    <span id="academicTermHelp" class="mt-1.5 block text-xs text-[#64748B]">Imports are applied only to the globally active academic term.</span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Import Coverage <span class="text-[#B91C1C]">*</span></span>
                    <select name="coverage" id="coverage" required>
                        <option value="all" @selected(old('coverage', 'all') === 'all')>All Programs in CSV</option>
                        <option value="specific" @selected(old('coverage') === 'specific')>Single Program</option>
                    </select>
                    <span class="mt-1.5 block text-xs text-[#64748B]">All Programs uses each validated row's mapped program.</span>
                </label>

                <label class="block sm:col-span-2" id="programField" hidden>
                    <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Program <span class="text-[#B91C1C]">*</span></span>
                    <select name="coverage_program_id" id="coverageProgram">
                        <option value="">Select official program</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" @selected(old('coverage_program_id') == $program->id)>{{ $program->code }} &middot; {{ $program->name }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1.5 block text-xs text-[#64748B]">Rows with a different mapped program will be rejected.</span>
                </label>
            </div>
        </div>

        <div class="p-6">
            <h2 id="upload-csv-title" class="text-base font-semibold text-[#172033]">Upload CSV File</h2>
            <p class="mt-0.5 text-sm text-[#64748B]">Official 9-column format: <span class="mono text-xs text-[#334155]">student_number, first_name, middle_name, last_name, suffix, program, year_level, academic_year, semester</span>.</p>

            <label for="csv" id="csvDropzone"
                class="mt-4 flex min-h-40 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-[#CBD3E1] bg-[#F8F9FC] px-6 py-8 text-center transition-colors hover:border-[#6D28A8] hover:bg-[#F3ECFA]/50">
                <span class="icon-well !h-11 !w-11 !rounded-xl" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                </span>
                <span class="text-sm font-semibold text-[#172033]">Drop your CSV file here or click to browse</span>
                <span class="text-xs text-[#64748B] num">CSV only &middot; Maximum {{ number_format($maxMegabytes) }} MB</span>
                <input id="csv" type="file" name="csv" accept=".csv,text/csv" required class="sr-only">
            </label>

            <div id="fileValidationMessage" role="status" hidden class="mt-3 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 py-2.5 text-sm text-[#B91C1C]"></div>

            <div id="selectedFile" hidden class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[#E3D4F3] bg-[#F3ECFA]/50 px-4 py-3.5">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex h-10 w-11 shrink-0 items-center justify-center rounded-lg border border-[#E3D4F3] bg-white text-xs font-bold text-[#6D28A8]">CSV</span>
                    <div class="min-w-0">
                        <p id="selectedFileName" class="truncate text-sm font-semibold text-[#172033]"></p>
                        <p class="text-xs text-[#64748B] num">
                            <span id="selectedFileSize"></span> &middot; <span id="selectedFileStatus" class="font-semibold text-[#15803D]">Ready to validate</span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="replaceFile" class="btn secondary btn-sm">Replace</button>
                    <button type="button" id="removeFile" class="btn text btn-sm">Remove</button>
                </div>
            </div>

            <p class="mt-4 rounded-lg border border-[#E2E6EF] bg-[#F8F9FC] px-4 py-3 text-xs leading-relaxed text-[#475569]">
                <strong class="font-semibold text-[#172033]">Import safety:</strong> Preview and column mapping never modify permanent records. Enrollment imports never print, release, replace, or revoke physical IDs.
            </p>
        </div>

        <footer class="flex items-center justify-end gap-2.5 border-t border-[#E2E6EF] bg-[#F8F9FC] px-6 py-4">
            <a class="btn secondary" href="{{ route('students.index') }}">Cancel</a>
            <button class="btn primary" type="submit" id="continueButton" disabled>Continue to Map Columns</button>
        </footer>
    </form>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('csvUploadForm');
    const term = document.getElementById('academicTerm');
    const coverage = document.getElementById('coverage');
    const programField = document.getElementById('programField');
    const program = document.getElementById('coverageProgram');
    const input = document.getElementById('csv');
    const dropzone = document.getElementById('csvDropzone');
    const selected = document.getElementById('selectedFile');
    const message = document.getElementById('fileValidationMessage');
    const continueButton = document.getElementById('continueButton');
    const maxBytes = {{ (int) config('idms.import_max_kilobytes', 25600) * 1024 }};
    let validFile = false;

    const validateFile = () => {
        const file = input.files?.[0];
        validFile = false;
        message.hidden = true;
        selected.hidden = true;
        dropzone.hidden = false;
        if (!file) return sync();
        const extensionOk = file.name.toLowerCase().endsWith('.csv');
        if (!extensionOk || file.size > maxBytes || file.size === 0) {
            message.textContent = !extensionOk ? 'Select a CSV file. Other file types are not supported.' : (file.size === 0 ? 'The selected CSV is empty.' : 'The selected file exceeds the {{ number_format($maxMegabytes) }} MB upload limit.');
            message.hidden = false;
            input.value = '';
            return sync();
        }
        validFile = true;
        document.getElementById('selectedFileName').textContent = file.name;
        document.getElementById('selectedFileSize').textContent = file.size < 1048576 ? `${(file.size / 1024).toFixed(1)} KB` : `${(file.size / 1048576).toFixed(2)} MB`;
        selected.hidden = false;
        dropzone.hidden = true;
        sync();
    };
    const sync = () => {
        const single = coverage.value === 'specific';
        programField.hidden = !single;
        program.disabled = !single;
        program.required = single;
        continueButton.disabled = !(term.value && validFile && (!single || program.value));
    };
    input.addEventListener('change', validateFile);
    [term, coverage, program].forEach(element => element.addEventListener('change', sync));
    document.getElementById('removeFile').addEventListener('click', () => { input.value = ''; validFile = false; validateFile(); });
    document.getElementById('replaceFile').addEventListener('click', () => input.click());
    ['dragenter', 'dragover'].forEach(name => dropzone.addEventListener(name, event => { event.preventDefault(); dropzone.classList.add('border-[#6D28A8]', 'bg-[#F3ECFA]'); }));
    ['dragleave', 'drop'].forEach(name => dropzone.addEventListener(name, event => { event.preventDefault(); dropzone.classList.remove('border-[#6D28A8]', 'bg-[#F3ECFA]'); }));
    dropzone.addEventListener('drop', event => { if (event.dataTransfer.files.length) { input.files = event.dataTransfer.files; validateFile(); } });
    form.addEventListener('submit', event => {
        sync();
        if (continueButton.disabled) { event.preventDefault(); form.reportValidity(); return; }
        continueButton.disabled = true;
        continueButton.textContent = 'Validating CSV...';
    });
    sync();
})();
</script>
@endpush
